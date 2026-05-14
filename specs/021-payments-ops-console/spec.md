---
## REQUIRED CONTEXT (loaded before executing this command)

Context files read: .specify/memory/constitution.md, .specify/memory/project-index.md,
.specify/memory/api-registry.md, docs/specs/01_PRD.md, docs/specs/09_Phasing_Plan.md,
docs/specs/11_DB_Schema.md, docs/specs/10_Package_List.md
---

# Feature Specification: Payments Operations Console

**Feature Branch**: `021-payments-ops-console`
**Created**: 2026-05-04
**Status**: Draft
**Phase ID**: ⚠️ Phase 4.3 — Payments Operations Console (PHASE BACKFILL NEEDED — add to `docs/specs/09_Phasing_Plan.md`)
**ADR Required**: `docs/adr/ADR-0019-payments-ops-console.md`
**Depends On**: Phase 4.0 (Paymob Gateway), Phase 4.1 (Refunds)

---

## PRD Traceability

- **FR-29** (admin approval and oversight workflows): partial coverage — admin monitoring and manual intervention over payment states.
- **Admin Journey §6.3**: "Handle settlements, wallet movements, and withdrawal approvals" — this console is the dedicated operations surface for that step.
- **⚠️ FR-EXT-001 – FR-EXT-010**: Payment recovery and gateway ops flows are **not explicitly stated in FR-1…FR-30**. These requirements extend the PRD. **BACKFILL NEEDED: add to `docs/specs/01_PRD.md`.**

---

## Schema Traceability

**Existing tables (from `docs/specs/11_DB_Schema.md`):**
- `payments` — primary entity; rows with `status=failed` or `status=authorized` are the main subjects of this console.
- `gateway_webhook_logs` — source for the Webhook Replay tab; each row represents one inbound gateway event.
- `refunds` — chargeback resolution may trigger a wallet reversal that mirrors the refund flow.
- `wallet_ledger` — append-only; chargeback resolution posts a counter-entry here.
- `audit_logs` — every admin manual action (capture, void, replay, chargeback intake) MUST append here.

**New tables (⚠️ NOT YET in `docs/specs/11_DB_Schema.md` — SCHEMA BACKFILL NEEDED):**
- `payment_chargebacks` — tracks each chargeback case: `id`, `public_id`, `payment_id`, `reason` (JSON EN+AR), `gateway_case_id`, `status` ENUM(`open`, `under_review`, `won`, `lost`), `amount_minor`, `amount_currency`, `opened_at`, `resolved_at`, `admin_notes` (JSON EN+AR), `created_by`, `updated_by`, timestamps.
- `gateway_health_pings` — uptime telemetry: `id`, `gateway_code` CHAR(20), `latency_ms` UNSIGNED INT, `success` BOOLEAN, `checked_at` timestamp. Append-only (no `updated_at`).

---

## User Scenarios & Testing

### User Story 1 — Admin Recovers a Failed Payment (Priority: P1)

An admin on the platform operations team opens the Payments console and sees the **Failed Payments** tab. The list shows all payments that the gateway reported as failed, with failure reason, amount, booking reference, and time of failure. For each entry, the admin can either:

- **Retry** — initiates a new payment attempt linked to the same booking, subject to idempotency rules so a concurrent retry cannot double-charge.
- **Mark abandoned** — records that the booking's payment will not be retried, releasing any held inventory back to the available pool.

Without this capability, every failed payment requires a developer to run a console command or an admin to phone a customer manually, creating friction and revenue loss from transient gateway errors.

**Why P1**: Failed payments directly block booking completion and vendor earnings. Recovery must be self-service for operations staff.

**Independent Test**: Can be fully tested by seeding a `payment` row with `status=failed` and verifying that the Retry action creates a new `payment_attempt` row, and that Mark Abandoned releases the inventory reservation.

**Acceptance Scenarios**:

1. **Given** a payment exists with `status=failed`, **When** the admin clicks Retry, **Then** a new payment attempt is initiated for the same booking amount with a new idempotency key, the original payment row is NOT mutated, and an `audit_logs` entry is appended with `actor_id`, `action=payment_retry`, and the reason.
2. **Given** a payment exists with `status=failed`, **When** the admin clicks Mark Abandoned with a typed reason, **Then** the payment status transitions to `abandoned`, the booking's `payment_status` updates accordingly, any held inventory reservations are released, and an `audit_logs` entry is appended.
3. **Given** the admin is not authenticated or lacks `manage_payments` permission, **When** accessing the Failed Payments tab, **Then** the response is a 403 / redirect to login.
4. **Given** a payment is already `captured` or `abandoned`, **When** the admin attempts to click Retry, **Then** the action is disabled with an explanatory message.

---

### User Story 2 — Admin Captures or Voids a Stuck Authorization (Priority: P2)

When a booking is confirmed and payment is authorized at the gateway, the authorized funds must be captured within a gateway-defined window (typically 24–72 hours) or the authorization expires. The **Stuck Authorizations** tab lists every payment with `status=authorized` that was authorized more than 24 hours ago. For each, the admin can:

- **Manually Capture** — triggers the gateway capture call with a mandatory reason, logs the capture to `audit_logs`, and advances the booking `payment_status` to `paid`.
- **Void** — releases the authorization at the gateway, reverses any held inventory, logs the void, and transitions the booking back to a pre-payment state.

**Why P2**: Authorization expiry causes silent fund release with no platform record. Manual recovery prevents customer disputes and protects vendor bookings already in preparation.

**Independent Test**: Can be tested by seeding a `payment` row with `status=authorized` and `created_at` older than 24 hours, then verifying each action produces the correct downstream state changes.

**Acceptance Scenarios**:

1. **Given** a payment has `status=authorized` and `authorized_at` > 24h ago, **When** the admin performs Manual Capture with a reason, **Then** the payment transitions to `captured`, the booking `payment_status` becomes `paid`, a commission calculation is triggered, and an `audit_logs` entry is created with `action=manual_capture` and the reason text.
2. **Given** a payment has `status=authorized` and `authorized_at` > 24h ago, **When** the admin performs Void with a reason, **Then** the gateway authorization is released, the payment transitions to `voided`, inventory reservations tied to this booking are released, and an `audit_logs` entry is created with `action=manual_void`.
3. **Given** a payment is less than 24h old with `status=authorized`, **When** the admin views the Stuck Authorizations tab, **Then** that payment does NOT appear in the list.
4. **Given** the gateway call fails during Manual Capture, **When** the admin retries, **Then** no duplicate capture is sent (idempotency enforced), and the failure is logged.

---

### User Story 3 — Admin Replays a Webhook Without Double-Charging (Priority: P3)

When a payment webhook fails to arrive (network issue, platform downtime), the booking remains in a state where the customer was charged at the gateway but the platform did not record the capture. The **Webhook Replay** tab shows `gateway_webhook_logs` entries that can be re-dispatched. The admin selects a log row and triggers replay.

The replay must be idempotency-safe: if the platform already processed an identical event (same gateway reference + event type), the replay produces the same final state without creating duplicate records.

**Why P3**: Missing webhooks create silent payment discrepancies that break vendor settlement. Safe replay is a zero-risk recovery mechanism.

**Independent Test**: Can be tested by creating a `gateway_webhook_logs` row that was never processed, triggering replay, verifying the payment status updates correctly; then replaying the same row a second time and verifying no additional state changes occur.

**Acceptance Scenarios**:

1. **Given** a `gateway_webhook_logs` row exists for a `payment_captured` event that was never processed, **When** the admin triggers Replay, **Then** the `ProcessPaymobWebhookAction` is re-invoked with the original payload, the payment record updates to `captured`, the booking `payment_status` updates to `paid`, and an `audit_logs` entry is created with `action=webhook_replayed`.
2. **Given** the same `gateway_webhook_logs` row is replayed a second time, **When** the replay is triggered, **Then** the system detects the prior successful processing via idempotency, returns the same final state without creating duplicate records, and no additional `audit_logs` entry is created.
3. **Given** a `gateway_webhook_logs` row contains an unrecognized event type, **When** the admin attempts replay, **Then** an error message is shown explaining the event type is not replayable, and no action is taken.
4. **Given** the admin lacks `manage_webhooks` permission, **When** attempting replay, **Then** the action is forbidden.

---

### User Story 4 — Admin Intakes and Tracks a Chargeback (Priority: P4)

When a bank or the payment gateway notifies (typically via email) that a customer has disputed a charge, the admin opens the **Chargebacks** tab and manually enters the dispute details: the related payment, the amount disputed, the gateway case ID, and the reason. The system creates a `payment_chargebacks` row, immediately posts a counter-entry to the vendor's `wallet_ledger` to reverse the credit (pending resolution), and tracks the case through to resolution.

**Why P4**: Chargebacks that go untracked result in un-reversed wallet credits and financial exposure. Intake even before automated gateway webhooks protects platform funds.

**Independent Test**: Can be tested by creating a chargeback intake form submission linked to an existing payment and verifying a `payment_chargebacks` row is created and a `wallet_ledger` reversal entry is posted.

**Acceptance Scenarios**:

1. **Given** a completed payment exists, **When** the admin fills the chargeback intake form (payment reference, amount, gateway case ID, reason in EN+AR) and submits, **Then** a `payment_chargebacks` row is created with `status=open`, a debit entry is appended to `wallet_ledger` for the vendor's wallet reversing the original commission credit, and an `audit_logs` entry is created with `action=chargeback_opened`.
2. **Given** an open chargeback case exists, **When** the admin updates the status to `won` or `lost` with resolution notes, **Then** the `payment_chargebacks` row updates, if `won` a credit is re-posted to the vendor wallet, and if `lost` no further ledger entry is needed (the earlier reversal stands), and an `audit_logs` entry is appended.
3. **Given** a payment does not exist or is not in `captured` status, **When** the admin attempts to link a chargeback to it, **Then** validation prevents the intake with a clear error message.
4. **Given** a chargeback is in `resolved` status (`won` or `lost`), **When** the admin attempts to reopen it, **Then** the action is blocked.

---

### User Story 5 — Admin Monitors Gateway Health (Priority: P5)

The **Gateway Health** tab shows a 24-hour summary of automated health pings to the payment gateway: success rate as a percentage, average latency in milliseconds, and a timeline of any outage windows (periods where pings failed consecutively). This allows admin to proactively identify degradation before it affects live bookings.

**Why P5**: Gateway degradation that goes undetected causes customer-facing checkout failures. Early visibility allows admin to pause marketing or set customer expectations.

**Independent Test**: Can be tested by seeding `gateway_health_pings` rows with known success/failure patterns and verifying that the displayed metrics (success rate, avg latency, outage windows) match the expected computed values.

**Acceptance Scenarios**:

1. **Given** `gateway_health_pings` rows exist for the last 24 hours, **When** the admin opens the Gateway Health tab, **Then** the correct success rate percentage and average latency are displayed, calculated from the actual rows.
2. **Given** 3 or more consecutive failed pings exist within a 15-minute window, **When** the admin views the tab, **Then** the window is highlighted as a detected outage with start and end times.
3. **Given** no pings have been recorded in the last 24 hours (e.g., the scheduled job is broken), **When** the admin opens the tab, **Then** an alert is shown indicating the health monitoring is not running.
4. **Given** the scheduled ping job fires every 5 minutes, **When** it runs, **Then** exactly one `gateway_health_pings` row is inserted per execution, with accurate `latency_ms` and `success` values.

---

### User Story 6 — Admin Spot-Checks the Reconciliation Diff (Priority: P6)

The **Reconciliation Quick-Diff** tab shows today's counts: number of payments the gateway reports as captured versus number of payments the platform records as `status=captured`. Any mismatch is highlighted immediately. This is a lightweight sanity check before the end-of-day settlement run, not a full reconciliation audit.

**Why P6**: Discrepancies between gateway and platform records — even a single missed webhook — mean a vendor may be paid for a booking that actually failed. The diff surfaces this before settlement runs.

**Independent Test**: Can be tested by creating a known discrepancy (gateway count > platform count) and verifying that the diff tab reports the correct numbers and highlights the mismatch.

**Acceptance Scenarios**:

1. **Given** the platform has 100 captured payments today and the gateway reports 100, **When** the admin opens the Reconciliation tab, **Then** the counts match, a green "No discrepancy" indicator is shown, and a manual refresh button is available.
2. **Given** the gateway reports 101 captured payments but the platform records only 100, **When** the admin opens the Reconciliation tab, **Then** the mismatch is highlighted in amber/red, the discrepancy count is shown (e.g., "+1 at gateway"), and a note prompts the admin to check the Webhook Replay tab.
3. **Given** the reconciliation query takes longer than expected, **When** the tab loads, **Then** the results are fetched asynchronously and a loading state is shown rather than a timeout error.

---

### Edge Cases

- What happens when a Manual Capture fails because the gateway authorization has already expired?
  → The action should return a meaningful error, transition the payment to `authorization_expired`, log the event, and prompt the admin to initiate a new payment link for the customer.
- What happens when a webhook replay triggers for a payment that was already abandoned by admin?
  → The idempotency check should detect the conflicting state and surface a warning rather than silently updating.
- What happens if two admins simultaneously try to manually capture the same stuck authorization?
  → The first capture should succeed; the second should receive a conflict error (the payment is no longer in `authorized` state).
- What happens when a chargeback amount exceeds the original payment amount?
  → The intake form should block submission with a validation error on the amount field.
- What happens when gateway health pings table grows very large?
  → Queries for the 24-hour window must use the `checked_at` index; rows older than 30 days can be pruned by a scheduled cleanup job.

---

## Requirements

### Functional Requirements

- **FR-EXT-001** ⚠️: Admin MUST be able to view all payments with `status=failed`, filtered by date range, amount range, and booking reference. *(BACKFILL NEEDED in 01_PRD.md)*
- **FR-EXT-002** ⚠️: Admin MUST be able to retry a failed payment, producing a new idempotency-safe payment attempt without mutating the original payment record. *(BACKFILL NEEDED)*
- **FR-EXT-003** ⚠️: Admin MUST be able to mark a failed payment as abandoned, triggering inventory release and booking status update. *(BACKFILL NEEDED)*
- **FR-EXT-004** ⚠️: Admin MUST be able to manually capture a payment that has been in `authorized` state for more than 24 hours, with a required reason that is appended to `audit_logs`. *(BACKFILL NEEDED)*
- **FR-EXT-005** ⚠️: Admin MUST be able to void a stuck authorization, releasing gateway-held funds and platform inventory reservations. *(BACKFILL NEEDED)*
- **FR-EXT-006** ⚠️: Admin MUST be able to select a `gateway_webhook_logs` row and re-dispatch it through the same processing pipeline, with idempotency ensuring no duplicate state changes. *(BACKFILL NEEDED)*
- **FR-EXT-007** ⚠️: Admin MUST be able to manually intake a chargeback dispute by linking it to an existing captured payment and providing reason (EN+AR), amount, and gateway case ID. *(BACKFILL NEEDED)*
- **FR-EXT-008** ⚠️: The system MUST automatically post a wallet ledger reversal when a chargeback is opened, and conditionally re-credit when a chargeback is won. *(BACKFILL NEEDED)*
- **FR-EXT-009** ⚠️: The system MUST record a gateway health ping every 5 minutes, storing latency and success status per gateway. *(BACKFILL NEEDED)*
- **FR-EXT-010** ⚠️: Admin MUST be able to view a real-time comparison of today's captured payment count at the gateway versus the platform record, with a highlighted mismatch indicator. *(BACKFILL NEEDED)*
- **FR-29** (existing): Admin must be able to review and approve withdrawal requests — this console complements that by ensuring the underlying payment data is accurate before settlement runs.

### Non-Functional Requirements

- Every admin action in this console (capture, void, retry, replay, chargeback intake/resolve) MUST append to `audit_logs` with actor, action type, target entity, reason, and before/after states.
- Webhook replay MUST be idempotency-safe — identical replay of any event produces the same final state with no duplicate records.
- Gateway health ping schedule MUST run every 5 minutes without blocking the main request queue.
- All chargeback reason and admin notes fields MUST be bilingual (EN + AR) using JSON translatable columns.
- All console tabs MUST be accessible only to users with the appropriate Spatie permission (e.g., `manage_payments`, `replay_webhooks`, `manage_chargebacks`, `view_gateway_health`).

### Key Entities

- **Payment** (`payments` table): Represents a single payment transaction; key statuses are `pending`, `authorized`, `captured`, `failed`, `abandoned`, `voided`. Mutable only on `status` and `gateway_response_log`.
- **Gateway Webhook Log** (`gateway_webhook_logs` table): Append-only record of every inbound gateway event; used as the source of truth for webhook replay.
- **Payment Chargeback** (`payment_chargebacks` — NEW): Tracks a bank/gateway dispute case from intake through resolution; linked 1:1 to a payment; drives the wallet reversal flow.
- **Gateway Health Ping** (`gateway_health_pings` — NEW): Append-only telemetry record produced by the scheduled health command every 5 minutes.
- **Audit Log** (`audit_logs` table): Append-only, polymorphic record of every admin manual action in this console.

---

## Success Criteria

### Measurable Outcomes

- **SC-001**: An admin can identify, investigate, and act on any failed payment in under 3 minutes from opening the console — no developer intervention required.
- **SC-002**: Replaying a previously unprocessed webhook event results in the correct final booking and payment state 100% of the time, with zero instances of double-charge or duplicate ledger entries.
- **SC-003**: Manually capturing or voiding a stuck authorization takes under 60 seconds from identification to confirmed audit log entry.
- **SC-004**: A chargeback intake form submission completes in under 30 seconds and results in an immediate wallet ledger adjustment visible on the vendor's wallet screen.
- **SC-005**: Gateway health data is no more than 5 minutes stale at any point during business hours.
- **SC-006**: The reconciliation diff is computed and displayed in under 10 seconds, reliably detecting any discrepancy of 1 or more payments between gateway count and platform count.
- **SC-007**: Every admin action taken via this console produces a verifiable `audit_logs` entry, making the full admin operations trail reconstructable from the audit log alone.
- **SC-008**: 100% of chargeback intakes that result in wallet reversals are idempotent — re-submitting the same intake form does not produce a duplicate ledger entry.

---

## Assumptions

- **Gateway API**: The Paymob gateway exposes a capture endpoint and a void endpoint that accept the gateway authorization reference. Their responses are synchronous enough for the admin to see the outcome within a single request cycle. (If async, the console will show "pending" status and update on the next health ping or webhook.)
- **Health ping**: The scheduled health command performs a lightweight call to the Paymob gateway status endpoint (or a synthetic `/ping` test charge at zero amount, if Paymob provides one). The exact mechanism is determined during ADR-0019 authoring.
- **Reconciliation data source**: The gateway's "today captured" count is fetched via the Paymob reporting API. If the API does not provide this, the diff falls back to comparing `gateway_webhook_logs` rows with `event_type=payment_captured` from today against `payments` rows with `status=captured` and `captured_at` today. The exact source is confirmed in ADR-0019.
- **Chargeback workflow**: Paymob does not have an automated chargeback webhook in Phase 1. Intake is always manual (admin reads an email from Paymob/bank and enters the details). Automated chargeback webhook processing is deferred to Phase 1.5.
- **Wallet reversal currency**: Chargeback reversal is always in EGP (the platform's operating currency for Phase 1). Multi-currency reversal is Phase 2.
- **Dual-approval**: Manual captures and voids do NOT require a second admin approval in Phase 1. If the platform enables dual-approval for high-value financial actions (per Phase 6.6 `wallet_adjustments`), this console should respect the same threshold setting from `app_settings`.
- **No new packages**: This feature requires no Composer packages beyond those already in `docs/specs/10_Package_List.md`. The health ping uses Laravel's built-in scheduler; the console uses Filament v3.

---

## Cut-List (inherited from phase)

- Chargeback dispute evidence upload (PDF/image attachments) → deferred to Phase 1.5 per user input.
- Multi-gateway routing rules and gateway selection logic → deferred to Phase 2 (only Paymob in Phase 1 per ADR-0005).
- Automated chargeback webhook processing from Paymob → deferred to Phase 1.5 (manual intake only in Phase 1).
- Full reconciliation audit report with line-item diff → covered by Phase 6.6 `ExportReconciliationCsvAction`; this feature provides only the quick-diff count.

---

## Phase Exit Criteria

- [ ] Admin can recover from any common payment failure mode through the console without developer intervention
- [ ] Webhook replay passes the idempotency test: same log row replayed twice produces identical final state
- [ ] Chargeback intake creates a wallet ledger reversal entry and the vendor's visible balance reflects the adjustment
- [ ] Gateway health tab shows accurate 24h success rate and latency from live `gateway_health_pings` data
- [ ] All Pest tests pass (green) including: idempotent replay, manual capture audit trail, void releases inventory, chargeback reversal, health ping schedule, reconciliation diff discrepancy detection
