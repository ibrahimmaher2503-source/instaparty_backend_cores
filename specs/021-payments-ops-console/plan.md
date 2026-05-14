# Implementation Plan: Payments Operations Console

**Branch**: `021-payments-ops-console` | **Date**: 2026-05-04 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `specs/021-payments-ops-console/spec.md`
**Phase**: ⚠️ 4.3 — Payments Operations Console (2 days) — PHASE BACKFILL NEEDED in `docs/specs/09_Phasing_Plan.md`

---

## Summary

The Payments Operations Console is a dedicated admin Filament page within the existing `Payments` module that gives platform operations staff a self-service toolkit for recovering from every common payment failure mode. It extends the Phase 4.0/4.1 Payments module (no new Laravel module) with seven Action classes, one Artisan command, two new tables (`payment_chargebacks`, `gateway_health_pings`), and a single Filament custom Page (`PaymentsOpsConsole`) with six tabs:

1. **Failed Payments** — list `payments.status=failed`; Retry and Mark-Abandoned actions
2. **Stuck Authorizations** — list `payments.status=authorized` older than 24h; Manual Capture and Void actions  
3. **Webhook Replay** — list `gateway_webhook_logs`; idempotency-safe re-dispatch action
4. **Chargebacks** — manual intake form; status tracking through to resolution
5. **Gateway Health** — 24h success rate and latency from `gateway_health_pings`
6. **Reconciliation Diff** — gateway captured count vs platform count, today

Every admin action appends to `audit_logs`. No new public API endpoints — this feature is admin-only Filament. The scheduled gateway health ping (`PingGatewayHealthCommand`) fires every 5 minutes via the Laravel scheduler.

---

## 1. ADR Reference

- **ADR-0019 — Payments Operations Console** (`docs/adr/0019-payments-ops-console.md`)
- **Status:** ⚠️ **Not yet authored** — must be created and accepted before any migration is written (Constitution Principle VI).
- **Why required per constitution:** The ADR captures non-obvious operational decisions for this feature: (a) the exact Paymob API used for gateway health pings (status endpoint vs synthetic zero-amount probe), (b) the reconciliation data source (Paymob reporting API vs `gateway_webhook_logs` count), (c) the idempotency mechanism for webhook replay (piggybacking existing `(gateway, gateway_ref) UNIQUE` constraint vs a new `replayed_at` column), and (d) the authorization threshold for manual capture/void (single admin vs dual-approval toggle from `app_settings`).
- **This is NOT a new-module ADR** — it is an ops-decision ADR for extending the existing Payments module. Format mirrors ADR-0005 §6 "Internal Decisions" section.

---

## 2. Constitution Check

| # | Principle | Applies? | How satisfied |
|---|---|---|---|
| **I** | Modular Monolith — no cross-module model imports | **Yes** | This feature extends `app/Modules/Payments/` using the same layer layout. Inventory release on void/abandon is done by firing `PaymentVoided` / `PaymentAbandoned` domain events — `Booking` module's listener handles the inventory; no direct import of Booking models. Commission trigger on manual capture is done by firing `PaymentCaptured` (the same event already consumed by `Settlement`). Wallet reversal on chargeback is done by firing `ChargebackOpened` — `Settlement` module listens and posts the wallet ledger entry. No Eloquent model from Settlement, Booking, or Catalog is imported into the Payments module. |
| **II** | Three Product Types — `match($enum)`, never if/elseif | **N/A** | This console is cross-type infrastructure. There are no per-type branches. The chargeback and gateway health flows have no product-type dimension. Architecture test `NoIfElseOnProductTypeStringTest` continues to pass with no changes. |
| **III** | Money Discipline — integer minor units + `Brick\Money` | **Yes** | `payment_chargebacks.amount_minor` (BIGINT UNSIGNED) + `amount_currency` (CHAR 3). Cast via reused `MoneyCast`. All comparison logic uses `Brick\Money\Money::isLessThanOrEqualTo()` — no float arithmetic. Architecture test `NoFloatForMoneyTest` covers this module automatically. |
| **IV** | Bilingual EN+AR — both mandatory | **Yes** | `payment_chargebacks.reason` and `payment_chargebacks.admin_notes` are JSON translatable columns. Both `en` and `ar` keys are required on the chargeback intake form. Filament uses translatable plugin EN/AR tabs. `OpenChargebackAction` validates `reason.en` and `reason.ar` non-empty. Translation files: `Payments/Resources/lang/{en,ar}/chargebacks.php`. Pest locale-parity test auto-covers new keys. |
| **V** | Append-Only Tables — no soft deletes, status-only updates | **Yes** | `gateway_health_pings` is fully immutable (no `updated_at`, no `deleted_at`). `payment_chargebacks` uses a `status` ENUM (mutable field) — all other columns are set on insert. Both comply. The existing `payments` table is updated only on `status`, `captured_at` per the existing contract — `ManualCapturePaymentAction` and `VoidStuckAuthorizationAction` write only these columns. Architecture test `AppendOnlyTablesHaveNoSoftDeletesTest` is extended to assert `gateway_health_pings` has no soft-delete trait. |
| **VI** | ADR Before Code | **⚠️ BLOCKED until ADR-0019 accepted** | Day 1 task 1 is: write ADR-0019, get Ibrahim's acknowledgement, mark `Accepted`. Migrations and Actions are gated behind this. |
| **VII** | Test-First for critical paths | **Yes** | Money flows (chargeback wallet reversal), idempotency (webhook replay), and state transitions (manual capture, void) all have Pest coverage written same-day as the Action. Coverage target ≥80% on all new Action classes. |
| **VIII** | Idempotency | **Partially applicable** | Webhook Replay reuses the existing idempotency guarantee: `gateway_webhook_logs` row re-dispatched through `ProcessPaymobWebhookAction` hits the same `(gateway, gateway_ref) UNIQUE` constraint on `payments` — no double-insert. The `ReplayWebhookAction` itself is guarded: if `gateway_webhook_logs.processed_at IS NOT NULL`, replay re-runs with the same idempotency path. Admin Actions (capture, void, chargeback intake) are NOT idempotency-key endpoints (they go through Filament, not a public API) — concurrency protection is provided by checking the `status` field at the start of the transaction and throwing `\DomainException` on stale state. |
| **IX** | Domain Events fire `DB::afterCommit` | **Yes** | `ChargebackOpened`, `ChargebackResolved`, `PaymentVoided`, `PaymentAbandoned` are all dispatched via `DB::afterCommit(fn () => event(...))` inside their respective Action transactions. Architecture test `EventsFireAfterCommitTest` covers the Payments module automatically. |
| **X** | Vendor Approval Two-Step Gate | **N/A** | No vendor approval logic in this feature. |
| **XI** | Document Storage | **N/A** | Chargeback evidence upload (PDFs) is explicitly deferred to Phase 1.5 per spec cut-list. No media uploads in Phase 1. |

**Phase 1 Forbidden Features check:** No vendor subscription tiers, no dispute resolution engine, no multi-gateway routing (multi-gateway deferred to Phase 2 per ADR-0005 §6.6). Multi-currency activation blocked — chargeback amounts MUST be EGP; runtime rejects other currencies in `OpenChargebackAction`.

**Gate result: CONDITIONAL PASS — ADR-0019 must be accepted before Day 1 migrations proceed.**

---

## 3. Schema

### Migration order (FK dependency)

```
1. {ts}_create_payment_chargebacks_table.php
2. {ts}_create_gateway_health_pings_table.php
```

Both migrations go in `app/Modules/Payments/Database/Migrations/` — not root `database/migrations/`.

**Cross-module prerequisite:** `payments` table (Phase 4.0) must exist before `payment_chargebacks` can reference it.

---

### 3.1 `payment_chargebacks` ⚠️ NEW TABLE

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | NO | `bigIncrements` |
| `public_id` | CHAR(26) UNIQUE | NO | ULID, exposed in Filament |
| `payment_id` | BIGINT UNSIGNED FK→`payments.id` | NO | `restrictOnDelete()` — chargebacks must outlive the payment row for audit |
| `gateway_case_id` | VARCHAR(190) | YES | Case reference from Paymob/bank; NULL until admin enters it |
| `reason` | JSON | NO | Translatable EN+AR — `{"en": "...", "ar": "..."}` |
| `status` | ENUM(`open`, `under_review`, `won`, `lost`) | NO | Default `open` |
| `amount_minor` | BIGINT UNSIGNED | NO | Disputed amount — validated ≤ `payments.amount_minor` |
| `amount_currency` | CHAR(3) | NO | EGP only Phase 1 |
| `opened_at` | TIMESTAMP | NO | Set on insert — not `created_at` alias; explicitly named for domain clarity |
| `resolved_at` | TIMESTAMP | YES | Set when `status` transitions to `won` or `lost` |
| `admin_notes` | JSON | YES | Translatable EN+AR — resolution notes, updated over time |
| `created_by` | BIGINT UNSIGNED FK→`users.id` | NO | Admin who opened; `restrictOnDelete()` |
| `updated_by` | BIGINT UNSIGNED FK→`users.id` | YES | Last admin to update; `restrictOnDelete()` |
| `created_at`, `updated_at` | TIMESTAMPS | NO | |

**Indexes:** `(payment_id)` — lookup all chargebacks for a payment; `(status, opened_at)` — filtered list for queue view.

**Charset:** `utf8mb4` / `utf8mb4_unicode_ci`.

**NOT soft-deleted** — chargebacks are financial records; they are never deleted, only resolved.

---

### 3.2 `gateway_health_pings` ⚠️ NEW TABLE — append-only

| Column | Type | Null | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | NO | `bigIncrements` |
| `gateway_code` | CHAR(20) | NO | `paymob` for Phase 1 |
| `latency_ms` | INT UNSIGNED | NO | Round-trip time in milliseconds; set to `0` on failure |
| `success` | TINYINT(1) | NO | 1 = gateway reachable; 0 = failure |
| `error_message` | VARCHAR(500) | YES | Short error description on failure |
| `checked_at` | TIMESTAMP | NO | `useCurrent()` — when the ping was dispatched |

**No `updated_at`** — fully immutable, append-only. No `deleted_at`.

**Indexes:** `(gateway_code, checked_at)` — all pings for a gateway in last 24h; `(success, checked_at)` — failure window detection.

**Partition note:** at 288 rows/day (every 5 min × 12 × 24), this table grows ~100k rows/year. No partitioning in Phase 1. Retention cleanup job (prune rows older than 90 days) deferred to Phase 7.0 hardening.

---

## 4. Per-Type Coverage

This feature is **cross-type administrative infrastructure**. None of the six console tabs are product-type-aware. `payment_chargebacks` and `gateway_health_pings` carry no `product_type` column. The underlying `payments` table already carries the booking's product-type context, but the ops console does not branch on it.

**No per-type Action classes are needed.** Architecture test `NoIfElseOnProductTypeStringTest` will pass without modification.

---

## 5. Locale Coverage

### 5.1 Translatable columns

| Table.Column | Where rendered | Validation |
|---|---|---|
| `payment_chargebacks.reason` | Filament intake form, chargeback list view | `OpenChargebackAction` requires both `reason.en` and `reason.ar` non-empty strings |
| `payment_chargebacks.admin_notes` | Filament detail view, resolve form | `ResolveChargebackAction` requires both `admin_notes.en` and `admin_notes.ar` when status transitions to `won` or `lost` |

### 5.2 Translation files

```
app/Modules/Payments/Resources/lang/en/chargebacks.php
app/Modules/Payments/Resources/lang/ar/chargebacks.php
```

Both files must have identical keys. Existing `LocaleParityTest` architecture test auto-covers them.

### 5.3 Filament locale tabs

`PaymentsOpsConsole` uses `filament/spatie-laravel-translatable-plugin` for the chargeback intake and resolve forms — EN and العربية tabs appear for `reason` and `admin_notes` fields.

### 5.4 Notification stubs

Admin-facing Filament notifications (`Notification::make()`) for action success/failure are in English only (admin panel language). No AR translation needed for Filament flash notifications per existing convention.

---

## 6. Idempotency

### 6.1 Webhook Replay — idempotency mechanism

The `ReplayWebhookAction` does not introduce a new idempotency mechanism. It reuses the existing invariant from Phase 4.0:

1. Load the `GatewayWebhookLog` by its `id`.
2. Re-dispatch the stored payload through `ProcessPaymobWebhookAction`.
3. `ProcessPaymobWebhookAction` will:
   - Attempt to insert/update `payments` using the `(gateway, gateway_ref) UNIQUE` constraint.
   - On duplicate key: the `payments` row already exists in the correct state — the action detects this and returns the existing row without mutation. The booking `payment_status` listener is NOT re-fired.
4. `gateway_webhook_logs.processed_at` is set on **first** successful processing only. Replay does not overwrite it.
5. Append `audit_logs` entry with `action=webhook_replayed` regardless of whether it was a true replay or a no-op duplicate.

**Concurrency:** two admins pressing Replay simultaneously hits the UNIQUE constraint — one succeeds, one receives a clean idempotent return. No locking required beyond the DB constraint.

### 6.2 Manual Capture / Void — concurrency protection

Both `ManualCapturePaymentAction` and `VoidStuckAuthorizationAction` open a DB transaction and immediately call:

```php
$payment = Payment::lockForUpdate()->findOrFail($id);
throw_unless($payment->status === PaymentStatus::Authorized, new \DomainException('payment_not_in_authorized_state'));
```

If two admins simultaneously try to capture the same authorization, one transaction wins; the other throws before any gateway call is made. The Filament UI refreshes and shows the updated status.

### 6.3 Chargeback Intake — duplicate prevention

`OpenChargebackAction` checks:

```php
throw_if(
    PaymentChargeback::where('payment_id', $payment->id)->where('status', '!=', 'resolved')->exists(),
    new \DomainException('active_chargeback_already_open')
);
```

This prevents two open chargebacks for the same payment. A previously resolved chargeback may be followed by a new one.

---

## 7. Domain Events

| Event | Where fired | `DB::afterCommit`? | Listener module | Queue |
|---|---|---|---|---|
| `Payments\Domain\Events\PaymentCaptured` | `ManualCapturePaymentAction::execute()` — **reuses existing event** | **Yes** | `Settlement\Application\Listeners\CalculateCommissionOnPaymentCaptured` (already registered in Phase 4.2); `Booking\Application\Listeners\UpdateBookingPaymentStatusListener` | `default` |
| `Payments\Domain\Events\PaymentVoided` | `VoidStuckAuthorizationAction::execute()` | **Yes** | `Booking\Application\Listeners\ReleaseInventoryOnPaymentVoidedListener` (NEW in Booking module — releases `service_inventory_reservations` for this booking) | `default` |
| `Payments\Domain\Events\PaymentAbandoned` | `MarkPaymentAbandonedAction::execute()` | **Yes** | `Booking\Application\Listeners\ReleaseInventoryOnPaymentVoidedListener` (same listener handles both voided + abandoned — booking-level release logic is the same) | `default` |
| `Payments\Domain\Events\ChargebackOpened` | `OpenChargebackAction::execute()` | **Yes** | `Settlement\Application\Listeners\ReverseWalletCreditOnChargebackOpenedListener` (NEW in Settlement module — posts debit `wallet_ledger` entry equal to the chargeback amount) | `default` |
| `Payments\Domain\Events\ChargebackResolved` | `ResolveChargebackAction::execute()` | **Yes** | `Settlement\Application\Listeners\HandleChargebackResolvedListener` (NEW in Settlement module — re-credits vendor wallet if `status=won`; no-op if `status=lost`) | `default` |

**Note:** `PaymentCaptured` reuse for manual capture is intentional — the Settlement commission flow and the Booking payment-status flip are valid consequences of a manual capture, exactly as for a webhook-driven capture. No new event or special-casing needed.

**Cross-module boundary rule:** `PaymentVoided`, `PaymentAbandoned`, `ChargebackOpened`, `ChargebackResolved` are dispatched by the Payments module and consumed by Booking and Settlement. Listeners are defined in their **owning module** (Booking and Settlement respectively) and registered in the **owning module's ServiceProvider**. The Payments ServiceProvider does NOT register cross-module listeners for these events — each consuming module registers its own listeners in `boot()`.

---

## 8. Actions — Full Inventory

### 8.1 `RetryFailedPaymentAction`

```
Path:     app/Modules/Payments/Application/Actions/RetryFailedPaymentAction.php
Input:    Payment $payment, string $adminReason
Guards:   $payment->status === PaymentStatus::Failed
Executes: DB::transaction → re-invoke InitiatePaymentAction with same booking/amount/method
          → new payments row (same booking_id, new public_id/gateway_ref)
          → original payment row unchanged
          → audit_logs: action=payment_retry, target=payments:{id}, reason=$adminReason
          → DB::afterCommit: no new event (InitiatePaymentAction fires PaymentInitiated)
Returns:  Payment (the new row)
```

**Why a new row instead of mutating the old one:** Append-only principle (Principle V). The original `failed` row is financial history. The retry is a new payment attempt. The booking can have multiple payment rows — the "current" one is the latest non-failed, non-abandoned row.

### 8.2 `MarkPaymentAbandonedAction`

```
Path:     app/Modules/Payments/Application/Actions/MarkPaymentAbandonedAction.php
Input:    Payment $payment, string $adminReason
Guards:   $payment->status === PaymentStatus::Failed
Executes: DB::transaction → Payment::lockForUpdate
          → $payment->update(['status' => PaymentStatus::Abandoned])
          → audit_logs: action=payment_abandoned
          → DB::afterCommit: PaymentAbandoned::dispatch($payment)
Returns:  Payment (updated)
```

### 8.3 `ManualCapturePaymentAction`

```
Path:     app/Modules/Payments/Application/Actions/ManualCapturePaymentAction.php
Input:    Payment $payment, string $adminReason
Guards:   $payment->status === PaymentStatus::Authorized, $payment->authorized_at < now()->subHours(24)
          (lockForUpdate prevents race)
Executes: DB::transaction
          → $gateway->capture($payment->gateway_ref, $payment->amount_minor, $payment->amount_currency)
          → on success: $payment->update(['status' => Captured, 'captured_at' => now()])
          → audit_logs: action=manual_capture, reason=$adminReason
          → DB::afterCommit: PaymentCaptured::dispatch($payment)  ← reuses existing event
          → on gateway failure: throw ManualCaptureGatewayException (no mutation)
Returns:  Payment (updated) or throws
```

### 8.4 `VoidStuckAuthorizationAction`

```
Path:     app/Modules/Payments/Application/Actions/VoidStuckAuthorizationAction.php
Input:    Payment $payment, string $adminReason
Guards:   $payment->status === PaymentStatus::Authorized
          (lockForUpdate prevents race)
Executes: DB::transaction
          → $gateway->void($payment->gateway_ref)
          → on success: $payment->update(['status' => PaymentStatus::Voided])
          → audit_logs: action=manual_void, reason=$adminReason
          → DB::afterCommit: PaymentVoided::dispatch($payment)
Returns:  Payment (updated) or throws
```

**PaymentGateway interface extension:** add `void(string $gatewayRef): VoidResult` to `PaymentsModule\Domain\Contracts\PaymentGateway`. Implement `PaymobGateway::void()` using Paymob's void API call. This extends Phase 4.0's interface without breaking it.

### 8.5 `ReplayWebhookAction`

```
Path:     app/Modules/Payments/Application/Actions/ReplayWebhookAction.php
Input:    GatewayWebhookLog $log
Guards:   $log->signature_valid === true (do not replay tampered events)
          $log->event_type in [transaction_processed, transaction_response_callback]
Executes: DB::transaction
          → Re-dispatch $log->payload through ProcessPaymobWebhookAction
          → Idempotency guaranteed by (gateway, gateway_ref) UNIQUE on payments
          → audit_logs: action=webhook_replayed, target=gateway_webhook_logs:{id}
          → No DB::afterCommit event from THIS action (ProcessPaymobWebhookAction fires its own)
Returns:  void
```

### 8.6 `OpenChargebackAction`

```
Path:     app/Modules/Payments/Application/Actions/OpenChargebackAction.php
Input:    Payment $payment, OpenChargebackDto $dto
          (dto: reason{en,ar}, gateway_case_id?, amount_minor, amount_currency)
Guards:   $payment->status === PaymentStatus::Captured
          $dto->amount_minor <= $payment->amount_minor
          No active (non-resolved) chargeback exists for this payment
Executes: DB::transaction
          → Insert PaymentChargeback row (status=open, opened_at=now())
          → audit_logs: action=chargeback_opened
          → DB::afterCommit: ChargebackOpened::dispatch($chargeback)
Returns:  PaymentChargeback
```

### 8.7 `ResolveChargebackAction`

```
Path:     app/Modules/Payments/Application/Actions/ResolveChargebackAction.php
Input:    PaymentChargeback $chargeback, ResolveChargebackDto $dto
          (dto: status{won|lost}, admin_notes{en,ar})
Guards:   $chargeback->status in [open, under_review]  (not already resolved)
Executes: DB::transaction
          → $chargeback->update(['status' => $dto->status, 'resolved_at' => now(), 'admin_notes' => $dto->admin_notes])
          → audit_logs: action=chargeback_resolved, before_status=..., after_status=...
          → DB::afterCommit: ChargebackResolved::dispatch($chargeback)  ← listener re-credits if won
Returns:  PaymentChargeback (updated)
```

---

## 9. Scheduled Command

### `PingGatewayHealthCommand`

```
Path:     app/Modules/Payments/Console/Commands/PingGatewayHealthCommand.php
Schedule: every 5 minutes (registered in PaymentsServiceProvider::boot() via Schedule)
```

**Execution flow:**

1. For each active gateway code (Phase 1: only `paymob`):
2. Start timer.
3. Call `PaymentGateway::ping(): PingResult` — a new method on the `PaymentGateway` interface. `PaymobGateway::ping()` makes a lightweight GET to Paymob's API (e.g., authentication health endpoint or `/status`). Exact URL confirmed in ADR-0019.
4. Record `latency_ms` = elapsed milliseconds; `success` = HTTP 200 with valid response; `error_message` = exception message on failure.
5. Insert one `gateway_health_pings` row.

**Failure handling:** If the HTTP call throws (timeout, DNS failure), the row is still inserted with `success=0` and `error_message`. The command does NOT throw — a failed ping is a data point, not a crash.

**Schedule registration in `PaymentsServiceProvider::boot()`:**

```php
$this->app->booted(function () {
    $schedule = $this->app->make(Schedule::class);
    $schedule->command(PingGatewayHealthCommand::class)
             ->everyFiveMinutes()
             ->withoutOverlapping()
             ->runInBackground();
});
```

---

## 10. Filament: `PaymentsOpsConsole`

```
Path: app/Modules/Payments/Filament/Pages/PaymentsOpsConsole.php
Navigation: "Payments" group, icon heroicon-o-wrench-screwdriver, label "Payments Console"
Permission: managed by filament-shield — generate permission `view_payments_ops_console`
```

### Tab architecture

Each tab is a standalone Filament `Tab` component within the Page. Tabs that display data use `InteractsWithTable` mixed in per-tab (via Filament v3 multi-table pages pattern — `$tables['failedPayments']` etc.). Each table has its own `query()` scope.

| Tab | Table source | Actions |
|---|---|---|
| Failed Payments | `Payment::where('status', 'failed')->latest()` | Row: `RetryAction`, `AbandonAction` (both with form modal requiring reason text) |
| Stuck Authorizations | `Payment::where('status', 'authorized')->where('created_at', '<', now()->subHours(24))` | Row: `CaptureAction`, `VoidAction` (both with form modal requiring reason text) |
| Webhook Replay | `GatewayWebhookLog::latest()` with filters for `signature_valid`, `event_type`, `processed_at` | Row: `ReplayAction` (disabled if `!signature_valid`; shows "already processed" badge if `processed_at` set) |
| Chargebacks | `PaymentChargeback::with('payment')->latest()` | Header: `IntakeChargebackAction` (form modal); Row: `ResolveChargebackAction` (form modal with status select + admin_notes EN/AR tabs) |
| Gateway Health | Custom view widget — queries `gateway_health_pings` for last 24h | Read-only: success rate %, avg latency ms, outage window list |
| Reconciliation Diff | Custom view widget — one gateway API call + one DB query | Read-only: count comparison with mismatch highlight; manual Refresh button |

### Columns — Failed Payments tab

```php
TextColumn::make('public_id')->label('Payment ID')->copyable(),
TextColumn::make('booking.public_id')->label('Booking'),
TextColumn::make('amount_minor')->money('EGP', divideBy: 100)->label('Amount'),
TextColumn::make('failure_code')->badge()->color('danger'),
TextColumn::make('created_at')->dateTime()->label('Failed At')->sortable(),
```

### Columns — Stuck Authorizations tab

```php
TextColumn::make('public_id')->copyable(),
TextColumn::make('booking.public_id'),
TextColumn::make('amount_minor')->money('EGP', divideBy: 100),
TextColumn::make('created_at')->dateTime()->label('Authorized At')->description(fn ($r) => $r->created_at->diffForHumans() . ' ago'),
```

### Columns — Webhook Replay tab

```php
TextColumn::make('id')->label('#'),
TextColumn::make('gateway')->badge(),
TextColumn::make('event_type')->badge()->color('info'),
IconColumn::make('signature_valid')->boolean(),
TextColumn::make('processed_at')->dateTime()->label('Processed At')->placeholder('Not processed'),
TextColumn::make('created_at')->dateTime()->label('Received At'),
```

### Columns — Chargebacks tab

```php
TextColumn::make('public_id')->copyable(),
TextColumn::make('payment.public_id')->label('Payment'),
TextColumn::make('amount_minor')->money('EGP', divideBy: 100),
TextColumn::make('status')->badge()->color(fn ($s) => match($s) {
    'open' => 'warning', 'under_review' => 'info', 'won' => 'success', 'lost' => 'danger',
}),
TextColumn::make('gateway_case_id')->placeholder('–'),
TextColumn::make('opened_at')->dateTime(),
TextColumn::make('resolved_at')->dateTime()->placeholder('Unresolved'),
```

### Gateway Health widget

Reads `gateway_health_pings` for `checked_at >= now()->subHours(24)` for each `gateway_code`. Computes:
- **Success rate** = `success=1 count / total count × 100`
- **Average latency** = `AVG(latency_ms)` where `success=1`
- **Outage windows** = contiguous runs of ≥3 consecutive `success=0` rows (ordered by `checked_at`)

If no rows exist in the last 24h, shows a red "Health monitoring not running" alert.

### Reconciliation Diff widget

Primary source: attempts `PaymobGateway::getTodayCapturedCount(): int` (new method on the interface and adapter — calls Paymob's reporting/transaction list API filtered by today's date and `status=captured`). If the gateway API call fails, falls back to counting `gateway_webhook_logs` rows where `event_type=transaction_processed` and `created_at >= today()` and `processed_at IS NOT NULL`.

Platform source: `Payment::where('status', 'captured')->whereDate('captured_at', today())->count()`.

Renders:
- Green badge when counts match.
- Amber badge when gateway > platform (missing webhooks).
- Red badge when platform > gateway (phantom captures — investigate immediately).

---

## 11. New Listener Stubs (other modules)

These listener stubs must be created alongside the Payments changes. They live in their owning modules.

### `Booking\Application\Listeners\ReleaseInventoryOnPaymentVoidedListener`

```
Path: app/Modules/Booking/Application/Listeners/ReleaseInventoryOnPaymentVoidedListener.php
Listens to: Payments\Domain\Events\PaymentVoided, Payments\Domain\Events\PaymentAbandoned
Action: Releases all service_inventory_reservations for the booking → status=released
        Updates bookings.payment_status = 'voided' | 'abandoned'
Queue: ShouldQueue, default queue
```

### `Settlement\Application\Listeners\ReverseWalletCreditOnChargebackOpenedListener`

```
Path: app/Modules/Settlement/Application/Listeners/ReverseWalletCreditOnChargebackOpenedListener.php
Listens to: Payments\Domain\Events\ChargebackOpened
Action: Appends debit wallet_ledger entry for the vendor's wallet
        Amount = chargeback.amount_minor (not full payment — disputed amount only)
        Entry type: chargeback_hold
Queue: ShouldQueue, default queue
```

### `Settlement\Application\Listeners\HandleChargebackResolvedListener`

```
Path: app/Modules/Settlement/Application/Listeners/HandleChargebackResolvedListener.php
Listens to: Payments\Domain\Events\ChargebackResolved
Action: if status=won → append credit wallet_ledger entry (reversal of the hold, type: chargeback_won)
        if status=lost → no-op (hold stands as permanent debit)
Queue: ShouldQueue, default queue
```

---

## 12. PaymentGateway Interface Extensions

The `PaymentGateway` interface (Phase 4.0) is extended with two new methods. Existing implementations (`PaymobGateway`) must add these. The interface extension is backward-compatible — no existing callers are affected.

```php
// app/Modules/Payments/Domain/Contracts/PaymentGateway.php — additions

/**
 * Void an authorized (uncaptured) transaction.
 */
public function void(string $gatewayRef): VoidResult;

/**
 * Probe the gateway's availability. Returns latency + success indicator.
 */
public function ping(): PingResult;

/**
 * Return today's captured transaction count per the gateway's own records.
 * May throw GatewayReportingUnavailableException if the reporting API is not accessible.
 */
public function getTodayCapturedCount(): int;
```

New value objects: `VoidResult` (DTO with `success: bool`, `message: string`) and `PingResult` (DTO with `success: bool`, `latencyMs: int`, `errorMessage: ?string`). Both live in `Payments\Application\DTOs\`.

---

## 13. API Documentation Plan

**No new public API endpoints** — this feature is entirely Filament admin UI. The API registry (`specs/memory/api-registry.md`) does NOT need new rows for this feature. No Scribe annotations, no Bruno collection entries, no Postman collection entries.

The single outbound integration is the Paymob gateway (for void, ping, and reconciliation count). These are outbound calls from Actions/Commands, not inbound API endpoints.

---

## 14. Packages Used

No new packages. All listed in `docs/specs/10_Package_List.md`.

| Package | Listed | Usage |
|---|---|---|
| `laravel/framework` | §1 | Scheduler, HTTP client for gateway calls |
| `brick/money` | §2 | Chargeback amount comparison and cast |
| `spatie/laravel-translatable` | §2 | `reason` and `admin_notes` JSON columns |
| `spatie/laravel-permission` | §2 | `view_payments_ops_console` permission |
| `spatie/laravel-activitylog` | §2 | audit_logs via existing `LogsActivity` trait on models |
| `filament/filament` | §3 | PaymentsOpsConsole page + 6 tabs |
| `filament/spatie-laravel-translatable-plugin` | §3 | EN/AR tabs on chargeback forms |
| `bezhansalleh/filament-shield` | §3 | Auto-generate console permission |
| `pestphp/pest` | §4 | All tests |

---

## 15. Architecture Tests (Added or Updated)

| Test | Purpose | New/Existing |
|---|---|---|
| `tests/Architecture/AppendOnlyTablesHaveNoSoftDeletesTest.php` | Assert `gateway_health_pings` has no `SoftDeletes` trait or `deleted_at` | **Existing — extend assertions** |
| `tests/Architecture/ManualCaptureFiresSameEventAsWebhookTest.php` | Assert `ManualCapturePaymentAction` dispatches `PaymentCaptured` — same class as `CapturePaymentAction`. Prevents accidental introduction of a parallel event type that bypasses Settlement listeners. | **New** |
| `tests/Architecture/EventsFireAfterCommitTest.php` | Auto-covers all 5 new events (`PaymentVoided`, `PaymentAbandoned`, `ChargebackOpened`, `ChargebackResolved`); no change to the test | **Existing — auto-covers** |
| `tests/Architecture/NoFloatForMoneyTest.php` | Auto-covers `payment_chargebacks.amount_minor` comparisons in new Actions | **Existing — auto-covers** |
| `tests/Architecture/LocaleParityTest.php` | Auto-covers new `lang/{en,ar}/chargebacks.php` | **Existing — auto-covers** |
| `tests/Architecture/GatewayInterfaceFullyImplementedTest.php` | Assert `PaymobGateway` implements all methods declared in `PaymentGateway` interface including `void()`, `ping()`, `getTodayCapturedCount()` | **New** |

---

## 16. Pest Test Plan

### Day 2 required tests (all must be green before phase exit)

#### `tests/Feature/Modules/Payments/WebhookReplayIdempotencyTest.php`

```php
it('replays an unprocessed webhook and updates payment status')
    ->group('payments', 'webhook-replay');

it('replaying the same log row twice produces identical final state with no duplicate records')
    ->group('payments', 'webhook-replay', 'idempotency');

it('replay is blocked for tampered webhook logs with signature_valid=false')
    ->group('payments', 'webhook-replay');

it('replay of an unrecognized event_type shows error and takes no action')
    ->group('payments', 'webhook-replay');
```

#### `tests/Feature/Modules/Payments/ManualCaptureTest.php`

```php
it('manual capture transitions payment to captured and creates audit log entry')
    ->group('payments', 'manual-capture');

it('manual capture fires PaymentCaptured event which triggers commission calculation')
    ->group('payments', 'manual-capture');

it('manual capture is rejected when payment is not in authorized state')
    ->group('payments', 'manual-capture');

it('two admins capturing the same payment simultaneously results in one capture and one conflict error')
    ->group('payments', 'manual-capture', 'concurrency');
```

#### `tests/Feature/Modules/Payments/VoidAuthorizationTest.php`

```php
it('void transitions payment to voided and releases inventory reservations')
    ->group('payments', 'void');

it('void creates an audit log entry with reason')
    ->group('payments', 'void');

it('void fires PaymentVoided event consumed by Booking module listener')
    ->group('payments', 'void');
```

#### `tests/Feature/Modules/Payments/ChargebackTest.php`

```php
it('chargeback intake creates payment_chargebacks row and fires ChargebackOpened event')
    ->group('payments', 'chargebacks');

it('ChargebackOpened event listener posts a wallet ledger debit for the vendor')
    ->group('payments', 'chargebacks');

it('chargeback resolution with status=won re-credits vendor wallet')
    ->group('payments', 'chargebacks');

it('chargeback resolution with status=lost does not re-credit vendor wallet')
    ->group('payments', 'chargebacks');

it('chargeback intake is blocked when amount exceeds payment amount')
    ->group('payments', 'chargebacks');

it('cannot open a second active chargeback for the same payment')
    ->group('payments', 'chargebacks');

it('chargeback intake requires both en and ar reason fields')
    ->group('payments', 'chargebacks', 'locale');
```

#### `tests/Feature/Modules/Payments/GatewayHealthTest.php`

```php
it('PingGatewayHealthCommand inserts one gateway_health_pings row per execution')
    ->group('payments', 'gateway-health');

it('health widget correctly computes success rate from seeded ping rows')
    ->group('payments', 'gateway-health');

it('health widget detects an outage window when 3+ consecutive pings fail')
    ->group('payments', 'gateway-health');

it('health widget shows alert when no pings exist in the last 24 hours')
    ->group('payments', 'gateway-health');
```

#### `tests/Feature/Modules/Payments/ReconciliationDiffTest.php`

```php
it('reconciliation diff returns zero when gateway and platform counts match')
    ->group('payments', 'reconciliation');

it('reconciliation diff highlights a synthetic discrepancy of 1 missed webhook')
    ->group('payments', 'reconciliation');

it('reconciliation falls back to webhook_log count when gateway reporting API is unavailable')
    ->group('payments', 'reconciliation');
```

---

## 17. Cut-List

**Deferred per spec (do not build in Phase 4.3):**

| Item | Deferred to |
|---|---|
| Chargeback evidence upload (PDF/image attachments) | Phase 1.5 |
| Multi-gateway routing rules | Phase 2 |
| Automated chargeback webhook from Paymob | Phase 1.5 |
| Full reconciliation audit export (line-item diff CSV) | Phase 6.6 `ExportReconciliationCsvAction` already planned |
| Partial refunds via chargeback resolution | Not in Phase 1 (full refunds only per ADR-0005) |
| `gateway_health_pings` retention cleanup job | Phase 7.0 Hardening |
| Dual-approval for Manual Capture/Void (above threshold) | Read `app_settings.dual_approval_threshold_minor` in Phase 1 — log a warning if exceeded but don't block; full dual-approval enforcement in Phase 1.5 |

---

## 18. Project Structure

### Documentation (this feature)

```
specs/021-payments-ops-console/
├── spec.md              ✅ created — /speckit.specify
├── plan.md              ✅ this file — /speckit.plan
└── checklists/
    └── requirements.md  ✅ created — /speckit.specify
```

### Source code (repository root)

```
app/Modules/Payments/
├── Application/
│   ├── Actions/
│   │   ├── RetryFailedPaymentAction.php           NEW
│   │   ├── MarkPaymentAbandonedAction.php          NEW
│   │   ├── ManualCapturePaymentAction.php          NEW
│   │   ├── VoidStuckAuthorizationAction.php        NEW
│   │   ├── ReplayWebhookAction.php                 NEW
│   │   ├── OpenChargebackAction.php                NEW
│   │   └── ResolveChargebackAction.php             NEW
│   └── DTOs/
│       ├── OpenChargebackDto.php                   NEW
│       ├── ResolveChargebackDto.php                NEW
│       ├── VoidResult.php                          NEW
│       └── PingResult.php                          NEW
├── Console/
│   └── Commands/
│       └── PingGatewayHealthCommand.php            NEW
├── Domain/
│   ├── Models/
│   │   ├── PaymentChargeback.php                   NEW
│   │   └── GatewayHealthPing.php                   NEW
│   ├── Enums/
│   │   └── ChargebackStatus.php                    NEW
│   ├── Events/
│   │   ├── ChargebackOpened.php                    NEW
│   │   ├── ChargebackResolved.php                  NEW
│   │   ├── PaymentVoided.php                       NEW
│   │   └── PaymentAbandoned.php                    NEW
│   └── Contracts/
│       └── PaymentGateway.php                      EXTEND (add void, ping, getTodayCapturedCount)
├── Infrastructure/
│   └── Gateways/
│       └── PaymobGateway.php                       EXTEND (implement void, ping, getTodayCapturedCount)
├── Filament/
│   └── Pages/
│       └── PaymentsOpsConsole.php                  NEW (6-tab custom Page)
├── Database/
│   └── Migrations/
│       ├── {ts}_create_payment_chargebacks_table.php   NEW
│       └── {ts}_create_gateway_health_pings_table.php  NEW
└── Resources/
    └── lang/
        ├── en/chargebacks.php                      NEW
        └── ar/chargebacks.php                      NEW

app/Modules/Booking/Application/Listeners/
└── ReleaseInventoryOnPaymentVoidedListener.php     NEW

app/Modules/Settlement/Application/Listeners/
├── ReverseWalletCreditOnChargebackOpenedListener.php   NEW
└── HandleChargebackResolvedListener.php                NEW

tests/
├── Feature/Modules/Payments/
│   ├── WebhookReplayIdempotencyTest.php            NEW
│   ├── ManualCaptureTest.php                       NEW
│   ├── VoidAuthorizationTest.php                   NEW
│   ├── ChargebackTest.php                          NEW
│   ├── GatewayHealthTest.php                       NEW
│   └── ReconciliationDiffTest.php                  NEW
└── Architecture/
    ├── ManualCaptureFiresSameEventAsWebhookTest.php NEW
    └── GatewayInterfaceFullyImplementedTest.php    NEW

docs/adr/
└── 0019-payments-ops-console.md                    NEW (Day 1 — required before migrations)
```

**Structure Decision:** Extension of `app/Modules/Payments/` (existing module). No new module. Listener stubs live in their owning modules (Booking, Settlement). All new files follow the canonical layer layout from the constitution and CLAUDE.md.

---

## Complexity Tracking

> No constitution violations to justify — all gates pass conditionally on ADR-0019 acceptance.

| Deviation | Justification | Alternative Rejected |
|---|---|---|
| `ManualCapturePaymentAction` fires existing `PaymentCaptured` event (not a new `ManualPaymentCaptured` event) | Reuse prevents Settlement and Communication listeners from missing the capture. Same financial consequences as webhook-driven capture. | New event rejected because it would require duplicating all downstream listeners — adding fragility with no benefit. |
| `gateway_health_pings` has no `public_id` ULID | This is an internal monitoring table with no external-facing API. No ULID needed (rule applies to top-level user-facing entities per CLAUDE.md §5). | Adding a ULID would be cargo-culting the convention to a telemetry row. |
| Reconciliation diff has a `getTodayCapturedCount()` gateway fallback to `gateway_webhook_logs` | Paymob reporting API availability is unconfirmed before ADR-0019. The fallback prevents the widget from being permanently broken if the API is unavailable. | Hard-dependency on Paymob reporting rejected because it makes the entire tab fail if the API is down. |
