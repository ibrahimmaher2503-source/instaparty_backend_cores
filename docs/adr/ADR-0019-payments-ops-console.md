# ADR-0019 — Payments Operations Console (Phase 4.3)

**Date:** 2026-05-04
**Status:** Accepted
**Author:** Ibrahim (Phase 4.3 scope extension — enhances 4.0 gateway + 4.1 refunds)
**Branch:** 021-payments-ops-console

---

## Context

After Phase 4.0 (Paymob gateway adapter) and Phase 4.1 (refund flow), the admin has no tooling to recover from operational payment failures: stuck authorizations, failed payment retries, gateway webhook processing failures, manual chargeback intake, or gateway health visibility. These failure modes are inevitable in production and must be recoverable without direct database access.

The console is admin-only (no customer-facing exposure) and extends the existing `app/Modules/Payments/` module. No new module is introduced.

This ADR resolves five design questions that must be settled before Day 1 migrations proceed.

---

## Decision 1: Paymob API Shape for Manual Void

**Question:** Which Paymob API endpoint supports voiding a captured authorization?

**Decision:** Use Paymob's `void` transaction API (`POST /api/acceptance/void_refund/void`) with the gateway transaction order ID. The `PaymentGateway` interface gains a new `void(string $gatewayRef): VoidResult` method. `VoidResult` is a DTO with `success: bool`, `gatewayRef: string`, `errorMessage: ?string`.

**Fallback:** If Paymob returns an error indicating the transaction is already captured (not just authorized), `VoidStuckAuthorizationAction` falls through to `ManualCapturePaymentAction` and then `ProcessRefundAction` to achieve the same net effect. The action logs the fallback path in `audit_logs`.

**Why:** Paymob's void endpoint is only available within a short window after authorization before settlement. The fallback ensures operators are not blocked if the window has passed.

---

## Decision 2: Gateway Health Ping Mechanism

**Question:** Should health pings use a Paymob status/ping API, or a synthetic test charge?

**Decision:** Use the **Paymob order inquiry API** (`GET /api/ecommerce/orders/{id}`) with a known stable order ID (stored in `app_settings.paymob_health_check_order_id`). A successful 200 response with a non-error body is recorded as `success=true`. Latency is the round-trip wall time in milliseconds. No test charge is initiated — that would create noise in merchant transaction history.

`PingResult` DTO: `success: bool`, `latencyMs: int`, `errorMessage: ?string`, `gatewayCode: string`.

**Why:** A status API call is read-only, produces no merchant-visible side effects, and exercises the full Paymob API path. A synthetic charge would require cleanup logic and create reconciliation noise.

**Fallback:** If `app_settings.paymob_health_check_order_id` is not set, `PingGatewayHealthCommand` logs a warning and records `success=false` with `error_message='health_check_order_id_not_configured'`. This forces explicit setup rather than silently skipping health pings.

---

## Decision 3: Reconciliation Diff Data Source

**Question:** Should the reconciliation quick-diff use the Paymob reporting API or count local `gateway_webhook_logs`?

**Decision:** **Dual-source with fallback.** Primary source is `PaymobGateway::getTodayCapturedCount()` — calls Paymob's transaction report API filtered to today's date and `transaction_type=CAPTURE`. If the call fails (network error, non-200, timeout), fall back to counting `gateway_webhook_logs` rows where `event_type = 'transaction_processed'` and `processed_at IS NOT NULL` and `DATE(checked_at) = today()`.

The Filament tab shows which data source was used in the current render via a badge ("Live from Gateway" vs "Local Webhook Log Fallback").

**Why:** Gateway reporting API availability is not guaranteed (rate limits, maintenance). The fallback prevents the reconciliation tab from being permanently broken. The source indicator ensures operators know to treat fallback counts as approximate.

---

## Decision 4: Idempotency Mechanism for Webhook Replay

**Question:** Does `ReplayWebhookAction` need a new idempotency table or mechanism?

**Decision:** **Reuse the existing `(gateway, gateway_ref) UNIQUE` constraint on `payments`.** `ReplayWebhookAction` re-dispatches the raw `payload` from the `gateway_webhook_logs` row through `ProcessPaymobWebhookAction`. The existing action already guards against duplicate payment creation via the UNIQUE index. A replay of an already-processed webhook results in the same final state — no double-charge is possible.

`ReplayWebhookAction` also checks `gateway_webhook_logs.processed_at IS NOT NULL` and records the replay event in `audit_logs` with `event='webhook_replayed'`, `subject_type='gateway_webhook_logs'`, `subject_id={log_id}`, `actor_id={admin_user_id}`.

**Why:** Introducing a new idempotency mechanism when the underlying payment creation is already idempotent by UNIQUE constraint would be redundant. The audit log is sufficient to trace replay history.

---

## Decision 5: No Dual-Approval Gate for Phase 1

**Question:** Should manual capture or void require a second admin to approve?

**Decision:** **No dual-approval gate in Phase 1.** Manual capture and void require only the acting admin's standard `manage_payments` Filament Shield permission. A `reason` freetext field (EN+AR) is required on the Filament action modal; the reason and acting admin ID are recorded in `audit_logs`. This provides an audit trail without blocking single-admin teams.

Dual-approval (maker-checker) is deferred to Phase 2 per the cut-list.

**Why:** InstaParty operates with a small admin team in Phase 1. Requiring a second approver would make urgent payment recovery impossible during off-hours. The mandatory audit log entry with reason satisfies compliance requirements at this scale.

---

## Schema Artifacts (2 new tables)

### `payment_chargebacks`

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT PK | |
| `public_id` | CHAR(26) UNIQUE | ULID |
| `payment_id` | BIGINT FK → `payments.id` | `restrictOnDelete` |
| `gateway_case_id` | VARCHAR(190) nullable | Paymob case reference, if any |
| `reason` | JSON | Translatable EN+AR |
| `status` | ENUM(open, under_review, won, lost) | status-only updates allowed |
| `amount_minor` | BIGINT UNSIGNED | |
| `amount_currency` | CHAR(3) | |
| `opened_at` | TIMESTAMP | |
| `resolved_at` | TIMESTAMP nullable | |
| `admin_notes` | JSON nullable | Translatable EN+AR |
| `created_by` | BIGINT FK → `users.id` | `restrictOnDelete` |
| `updated_by` | BIGINT FK → `users.id` nullable | `restrictOnDelete` |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

Indexes: `(payment_id)`, `(status, opened_at)`

### `gateway_health_pings` (append-only)

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT PK | |
| `gateway_code` | CHAR(20) | e.g. `paymob` |
| `latency_ms` | INT UNSIGNED | |
| `success` | TINYINT(1) | |
| `error_message` | VARCHAR(500) nullable | |
| `checked_at` | TIMESTAMP useCurrent() | append-only; NO `updated_at` |

Indexes: `(gateway_code, checked_at)`, `(success, checked_at)`

**No `public_id` ULID** — internal monitoring table with no external API exposure. The ULID rule applies to user-facing entities.

---

## New Application Actions (7)

| Action | Module | Purpose |
|---|---|---|
| `RetryFailedPaymentAction` | Payments | Re-invoke gateway charge on a `status=failed` payment |
| `MarkPaymentAbandonedAction` | Payments | Transition `status=failed` → `abandoned`; fires `PaymentAbandoned` |
| `ManualCapturePaymentAction` | Payments | Capture a `status=authorized` payment; fires **reused** `PaymentCaptured` |
| `VoidStuckAuthorizationAction` | Payments | Void a `status=authorized` payment via gateway; fires `PaymentVoided` |
| `ReplayWebhookAction` | Payments | Re-dispatch `gateway_webhook_logs` payload through `ProcessPaymobWebhookAction` |
| `OpenChargebackAction` | Payments | Create `payment_chargebacks` row; fires `ChargebackOpened` → wallet reversal |
| `ResolveChargebackAction` | Payments | Update chargeback status to `won`/`lost`; fires `ChargebackResolved` |

---

## Domain Events and Cross-Module Wiring

| Event | Fires from | Listener (module) | Effect |
|---|---|---|---|
| `PaymentCaptured` (REUSED) | `ManualCapturePaymentAction` | Settlement + Booking (existing) | Commission calculation + booking payment status update |
| `PaymentVoided` | `VoidStuckAuthorizationAction` | `Booking\ReleaseInventoryOnPaymentVoidedListener` | Release inventory hold |
| `PaymentAbandoned` | `MarkPaymentAbandonedAction` | `Booking\ReleaseInventoryOnPaymentVoidedListener` (same) | Release inventory hold |
| `ChargebackOpened` | `OpenChargebackAction` | `Settlement\ReverseWalletCreditOnChargebackOpenedListener` | Reverse vendor wallet credit |
| `ChargebackResolved` | `ResolveChargebackAction` | `Settlement\HandleChargebackResolvedListener` | Restore credit if won; finalize loss if lost |

All events fire `DB::afterCommit()` — never inside the transaction.

**`ManualCapturePaymentAction` fires `PaymentCaptured` (not a new `ManualPaymentCaptured` event)** to ensure existing Settlement commission listeners and Booking payment-status listeners trigger automatically without any new wiring.

---

## `PaymentGateway` Interface Extensions

Three new methods added to `Domain/Contracts/PaymentGateway.php`:

```php
public function void(string $gatewayRef): VoidResult;
public function ping(): PingResult;
public function getTodayCapturedCount(): int;
```

Both `PaymobGateway` (Infrastructure) and any test doubles must implement all three. Architecture test `GatewayInterfaceFullyImplementedTest` enforces this.

---

## `PingGatewayHealthCommand` Schedule

```php
$schedule->command(PingGatewayHealthCommand::class)
         ->everyFiveMinutes()
         ->withoutOverlapping()
         ->runInBackground();
```

Registered in `PaymentsServiceProvider::boot()` via `$this->callAfterResolving(Schedule::class, ...)`.

---

## Cut-List (deferred)

- **Chargeback evidence file upload** → Phase 1.5 (requires S3 presigned URL flow)
- **Multi-gateway routing rules** → Phase 2 (Paymob only in Phase 1)
- **Dual-approval (maker-checker) gate** → Phase 2

---

## Consequences

- `PaymentGateway` contract gains 3 new methods — all existing test doubles must be updated.
- `PingGatewayHealthCommand` must be registered in the scheduler and in `PaymentsServiceProvider`.
- `payment_chargebacks` and `gateway_health_pings` must be added to `docs/specs/11_DB_Schema.md` (⚠️ BACKFILL NEEDED).
- Phase 4.3 entry must be added to `docs/specs/09_Phasing_Plan.md` (⚠️ BACKFILL NEEDED).
- FR-EXT-001 through FR-EXT-010 must be added to `docs/specs/01_PRD.md` (⚠️ BACKFILL NEEDED).

---

## Exit Criteria

- [ ] Admin can recover from any common payment failure mode (retry, capture, void, abandon)
- [ ] Webhook replay is idempotent — same event replayed twice yields same final state
- [ ] Chargeback intake creates wallet adjustment via `ChargebackOpened` event
- [ ] Gateway health visible in Filament console with last-24h uptime + latency
- [ ] Pest suite green for all 6 user story test scenarios
