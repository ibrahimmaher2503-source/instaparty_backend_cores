# Feature Specification: Payments — Paymob Gateway + Per-Type Refunds

**Feature Branch**: `007-payments-paymob-refunds`
**Created**: 2026-05-02
**Status**: Draft
**Phase**: 4.0 — Payments: Paymob Gateway (2 days, Week 5) + 4.1 — Payments: Refunds (per-type) (1 day, Week 5)
**ADR**: `ADR-0008-payments-module.md` (to be created — see `/new-module-adr Payments`)

**PRD Coverage:**
- **FR-30** (partial — payment side of "Settlement logic must support commission deduction and booking-related financial tracking for Phase 1 baseline operations")
- **Tech Decisions §11** — per-type refund policies (Rental: 24h before event; Sale: until `in_preparation`; Digital: per `is_refundable_after_delivery` flag)

**Tables Touched (Schema §11, Module 6 — Payments):** `payments`, `payment_attempts`, `refunds`, `idempotency_keys`, `gateway_webhook_logs`. Read-only references: `bookings`, `booking_items`, `services` (and the three detail tables).

**Module Boundaries:** New `app/Modules/Payments/` module. Cross-module communication via domain events (`PaymentCaptured`, `PaymentFailed`, `RefundCompleted`) and one outbound contract (`PaymentGateway` interface in `Domain/Contracts/`). The Booking module subscribes to update `bookings.payment_status`. Settlement (Phase 4.2) will subscribe later to write `wallet_ledger` rows.

**Input (raw, for traceability):** Phase 4.0 + 4.1 from `docs/specs/09_Phasing_Plan.md` lines 628–696.

---

## Clarifications

### Session 2026-05-02

- Q: Refund initiator scope — admin-only or customer-initiated too? → A: Admin-only in Phase 1. Customer-side cancellation that triggers a refund is a separate (later) phase. The Filament admin action is the single entry point for refunds in this phase.
- Q: Pending-payment lifecycle when customer abandons or payment fails? → A: Each initiate call creates a NEW `payments` row (no reuse of `pending`/`failed` rows). A sweep job ages out `pending` rows once the booking's 24h payment hold expires by transitioning them to `failed` with `failure_code = 'expired_payment_hold'` (existing enum value — no schema change). Customer may retry freely; idempotency-key middleware handles same-call dedupe.
- Q: Refund reason taxonomy — fixed enum or free-form? → A: Fixed enum of 5 codes: `customer_request`, `vendor_cancellation`, `service_unavailable`, `duplicate_charge`, `admin_discretion`. Validated server-side; Filament uses a `Select`. Free-text context goes in the existing `reason_notes` translatable JSON column (EN+AR).
- Q: PCI scope — does InstaParty ever touch card data? → A: Zero card data on InstaParty servers, ever. Paymob hosted iframe/redirect is the only entry point. `payment_attempts.request_payload` and `gateway_webhook_logs.payload` redact any field that could contain PAN/CVV/expiry; an architecture test enforces the redaction allowlist. The system stays in PCI-DSS SAQ-A scope.
- Q: Webhook source-IP allowlist — defense in depth or HMAC alone? → A: HMAC-SHA512 signature verification only (no IP allowlist). Constant-time comparison via `hash_equals`. The decision and its rationale (Paymob can rotate IPs silently → ops risk outweighs marginal security gain) are documented in ADR-0008's "Alternatives Considered" section.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Customer Pays for Confirmed Booking via Paymob (Priority: P1)

A customer with a `confirmed` booking initiates payment. The system creates a payment record, calls Paymob to obtain a payment intent / hosted-checkout URL, and returns the redirect URL. The customer completes payment on Paymob's hosted page. Paymob fires a webhook back to InstaParty; the system verifies the HMAC signature, captures the payment, and updates the booking's `payment_status` to `paid`.

**Why this priority**: This is the only customer-facing money flow in Phase 1. Without it, no booking can complete, no commission can accrue, and no settlement can occur. Phase 4.2 (Settlement) is blocked until this story passes.

**Independent Test**: With a `confirmed` booking and a Paymob sandbox account, the customer initiates payment, completes the Paymob test card flow, and the system reflects `payments.status = captured` and `bookings.payment_status = paid` after the webhook is received. Refund flow not required.

**Acceptance Scenarios**:

1. **Given** a customer with a `confirmed` booking whose `payment_status = pending`, **When** the customer calls `POST /api/v1/customer/bookings/{ulid}/payments` with an `Idempotency-Key` header, **Then** a `payments` row is created with `status = pending`, a `payment_attempts` row records the outbound call, the response contains a Paymob redirect URL plus `payment.public_id`, and a `PaymentInitiated` event fires after commit.
2. **Given** a duplicate initiate call with the same `Idempotency-Key` within 24h, **When** the request arrives, **Then** the cached response is replayed, no second Paymob call is made, and no second `payments` row is created.
3. **Given** a Paymob webhook arrives at `POST /api/v1/webhooks/paymob` with a valid HMAC signature, **When** the request is processed, **Then** a `gateway_webhook_logs` row is appended with `signature_valid = true`, the matching `payments` row's `status` advances to `captured` (or `failed` for a failed event), `captured_at` is set, a `PaymentCaptured` event fires after commit, and the listener updates `bookings.payment_status` to `paid`.
4. **Given** a Paymob webhook arrives with an invalid HMAC signature, **When** the request is processed, **Then** a `gateway_webhook_logs` row is appended with `signature_valid = false`, the response is `401 Unauthorized`, no payment state is mutated, and the failure is auditable.
5. **Given** the same valid webhook is delivered twice (gateway retry), **When** both arrive, **Then** the payment state is captured exactly once — the second delivery is recognised as a duplicate via the `(gateway, gateway_ref)` UNIQUE constraint and acknowledged with `200 OK` without re-firing `PaymentCaptured`.
6. **Given** the customer initiates payment for a booking that is not in `confirmed` status, **Then** a `409 Conflict` is returned and no `payments` row is created.
7. **Given** the customer initiates payment for another customer's booking, **Then** a `403 Forbidden` is returned.
8. **Given** an unauthenticated request to initiate payment, **Then** a `401 Unauthorized` is returned.

---

### User Story 2 — Paymob Webhook Captures Payment Asynchronously (Priority: P1)

The webhook endpoint must accept Paymob's signed callbacks regardless of which `payments` row they refer to, idempotently move the payment through the state machine, and log every receipt for audit. The endpoint is unauthenticated (Paymob is the caller) and protected only by HMAC signature verification.

**Why this priority**: Paymob's hosted checkout returns asynchronously — the redirect to our success page is *not* the source of truth. Without webhook handling, payments never finalise. Story 1's success criterion depends entirely on this story working.

**Independent Test**: Replay a captured Paymob sandbox webhook payload (with valid signature) against `/api/v1/webhooks/paymob`. Verify `payments.status` becomes `captured`, `gateway_webhook_logs` is appended, and the listener updates `bookings.payment_status` — no customer interaction required.

**Acceptance Scenarios**:

1. **Given** a `payments` row in `pending` status with a known `gateway_ref`, **When** Paymob sends a `transaction_processed` callback with valid HMAC and `success: true`, **Then** the payment moves to `captured`, `captured_at` is set, and the booking's `payment_status` becomes `paid`.
2. **Given** a `payments` row in `pending` status, **When** Paymob sends a `transaction_processed` callback with valid HMAC and `success: false`, **Then** the payment moves to `failed`, `failure_code` and `failure_message` (translatable JSON) are stored, and the booking's `payment_status` remains `pending` (customer can retry).
3. **Given** a webhook payload that references an unknown `gateway_ref`, **When** processed, **Then** the row is logged in `gateway_webhook_logs` with `processing_error` describing the lookup failure, the response is `200 OK` (so Paymob does not retry indefinitely), and an admin alert is dispatched.
4. **Given** the webhook arrives while the matching booking has already been refunded or cancelled, **Then** the payment state moves to `failed` (capture rejected) and `processing_error` records the reason — no booking state mutation occurs.

---

### User Story 3 — Admin Refunds a Rental Booking Within Allowed Window (Priority: P2)

An admin opens a `paid` rental booking in Filament and presses "Issue refund". The system runs `RefundPolicyService::policyFor(ProductType::Rental)` against the booking's items: refund is allowed only when **every** rental item's `event_starts_at` is at least 24 hours in the future and no item has entered `setup` status. If allowed, the system creates a `refunds` row, calls Paymob's refund endpoint, and on success marks the booking `payment_status = refunded`.

**Why this priority**: Rentals carry the highest refund risk (security deposits, scheduled equipment). The 24h cutoff is the rule that protects vendor logistics; without enforcement, vendors can be left holding equipment they can no longer rebook.

**Independent Test**: Create a paid booking with a single rental item whose `event_starts_at` is 48h in the future. Issue refund as admin. Verify `refunds.status = completed`, booking moves to `payment_status = refunded`, and a `RefundCompleted` event is dispatched.

**Acceptance Scenarios**:

1. **Given** a `paid` booking with a rental item whose `event_starts_at` is more than 24h in the future and item status is not `setup`, **When** the admin issues a refund with reason and `Idempotency-Key`, **Then** a `refunds` row is created with `status = pending`, the gateway is called, on success the row moves to `completed`, the booking's `payment_status` becomes `refunded`, and a `RefundCompleted` event fires.
2. **Given** a `paid` booking with a rental item whose `event_starts_at` is exactly 24h away (boundary), **When** the admin attempts a refund, **Then** the refund is allowed (`>= 24h` is the inclusive boundary).
3. **Given** a `paid` booking with a rental item whose `event_starts_at` is less than 24h away, **When** the admin attempts a refund, **Then** the request is rejected with `422 Unprocessable Entity` and a translatable error message ("Rental refund window has closed").
4. **Given** a `paid` booking with a rental item already in `setup` status, **When** the admin attempts a refund, **Then** the request is rejected regardless of time window because logistics are already underway.
5. **Given** the gateway refund call fails, **Then** the `refunds` row is marked `failed`, the booking's `payment_status` remains `paid`, and the failure is logged for retry by an admin.

---

### User Story 4 — Admin Refunds a Sale Booking Before Preparation Begins (Priority: P2)

An admin issues a refund on a `paid` sale booking. `RefundPolicyService::policyFor(ProductType::Sale)` allows the refund only while every sale `booking_item` has `item_status` strictly less than `in_preparation`. Once a vendor has started preparing the order, the refund is rejected.

**Why this priority**: Sale items (cakes, perishables) cannot be returned once cooking has started. Mirrors rental in importance for the sale vertical.

**Independent Test**: Create a paid booking with a single sale item in `confirmed` (not yet `in_preparation`) status. Refund succeeds. Then advance status to `in_preparation` and verify a second refund attempt fails.

**Acceptance Scenarios**:

1. **Given** a `paid` sale booking whose item is in `confirmed` status, **When** the admin issues a refund, **Then** the refund is allowed and processes through the gateway.
2. **Given** a `paid` sale booking whose item has advanced to `in_preparation`, **When** the admin attempts a refund, **Then** the request is rejected with `422 Unprocessable Entity` and the message "Sale refund blocked — preparation has begun".
3. **Given** a `paid` sale booking whose item has advanced to `out_for_delivery` or `delivered`, **When** the admin attempts a refund, **Then** the request is rejected (preparation precedes both states).

---

### User Story 5 — Admin Refunds a Digital Booking Per Service Flag (Priority: P2)

An admin issues a refund on a `paid` digital booking. `RefundPolicyService::policyFor(ProductType::Digital)` reads the `service_digital_details.is_refundable_after_delivery` flag for each item: refund is allowed only if every digital item has the flag set to `true` OR none of them has reached `delivered` status yet.

**Why this priority**: Digital products often cannot be "returned" — once a redemption code is delivered, the customer has the value. The per-service flag (set by vendor on catalog upload) is the mechanism that lets vendors opt into post-delivery refunds (e.g., for templates that are revocable).

**Independent Test**: Create two paid digital bookings — one for a service with `is_refundable_after_delivery = true`, the other `false`. Both items in `delivered` state. Refund on the first succeeds; refund on the second is rejected.

**Acceptance Scenarios**:

1. **Given** a `paid` digital booking whose item is not yet in `delivered` status, **When** the admin issues a refund, **Then** the refund is allowed regardless of the service's `is_refundable_after_delivery` flag.
2. **Given** a `paid` digital booking whose item is in `delivered` status and the service has `is_refundable_after_delivery = true`, **When** the admin issues a refund, **Then** the refund is allowed.
3. **Given** a `paid` digital booking whose item is in `delivered` status and the service has `is_refundable_after_delivery = false`, **When** the admin attempts a refund, **Then** the request is rejected with the message "Digital product is not refundable after delivery".

---

### Edge Cases

- **Concurrent initiate calls** with different idempotency keys for the same booking: only one `pending` `payments` row per booking is allowed at a time (enforced via app-level lock on the booking); the second concurrent caller receives `409 Conflict` and is told an active payment intent already exists.
- **Customer abandons checkout** (browser closed, no webhook arrives): the `payments` row stays in `pending` until the sweep job (FR-PAY-010) ages it out at the booking's 24h hold expiry. Customer may re-initiate before the sweep runs only if the previous row has been swept or has reached `failed`.
- **Paymob signature using the wrong shared-secret variant** (sandbox vs production keys swapped): logged as `signature_valid = false` and rejected; admin must rotate keys before any further callbacks succeed.
- **Webhook arrives before our DB has committed the `payments` row** (race condition): the lookup fails, the webhook is logged with `processing_error = "payment not found"`, and Paymob's automatic retry resolves it on the next attempt.
- **Currency mismatch** between `payments.amount_currency` and the booking's `total_currency`: rejected at initiate time with `422` (Phase 1 is EGP-only; multi-currency is Phase 2 per Constitution §"Phase 1 Forbidden Features").
- **Refund issued on a partially-paid booking** (split payments): rejected — Phase 1 cut-list defers split payments. One payment per booking only.
- **Booking cancelled after payment captured but before refund issued**: the cancellation flow itself triggers a refund through the same `InitiateRefundAction` rather than mutating `payments` directly.
- **Paymob outage at refund time**: `refunds.status = failed`, the row remains in the table for an admin retry; no automatic retry job in Phase 1 (cut-list).
- **Idempotency key collision** across two different users: rejected with `409 Conflict` because the lookup is keyed by `(key, user_id, request_hash)`.

---

## Requirements *(mandatory)*

### Functional Requirements

**Payment initiation (Phase 4.0)**

- **FR-PAY-001**: System MUST expose `POST /api/v1/customer/bookings/{ulid}/payments` accepting an `Idempotency-Key` header, idempotency response replay within 24h, and returning a Paymob redirect URL plus `payment.public_id`. (Constitution VIII)
- **FR-PAY-002**: System MUST persist every payment in the `payments` table with `gateway`, `gateway_ref`, `amount_minor`, `amount_currency`, `method`, and `status` per Schema §6 — money columns stored as integer minor units, never floats. Each non-idempotent initiate call creates a NEW `payments` row; existing `pending` or `failed` rows MUST NOT be reused or mutated to a fresh `pending` state. (Constitution III)
- **FR-PAY-003**: System MUST log every outbound gateway call in `payment_attempts` with sanitized `request_payload`, `response_payload`, `http_status`, and `attempt_no` for forensic review. (Append-only — Constitution V) Sanitization MUST redact any field whose key matches a PAN/CVV/expiry pattern (allowlist of safe-to-log keys: `order_id`, `amount_cents`, `currency`, `merchant_order_id`, `integration_id`, `payment_key_token`, `transaction_id`, `success`, `error_occured`, plus Paymob's documented non-sensitive fields). An architecture test (`tests/Architecture/PaymentLoggingRedactionTest.php`) enforces the allowlist. The system MUST NOT accept, transmit, or persist raw card data anywhere — Paymob hosted iframe/redirect is the only customer entry point (PCI-DSS SAQ-A scope).
- **FR-PAY-004**: System MUST enforce a UNIQUE index on `(gateway, gateway_ref)` so duplicate captures are rejected at the database level. (Schema §6)

**Webhook processing (Phase 4.0)**

- **FR-PAY-005**: System MUST expose `POST /api/v1/webhooks/paymob` (no auth, public) and reject any payload whose HMAC-SHA512 signature does not match the configured shared secret using `hash_equals` constant-time comparison. No source-IP allowlist is applied — the HMAC is the sole authenticator (rationale recorded in ADR-0008's "Alternatives Considered").
- **FR-PAY-006**: System MUST persist every webhook receipt in `gateway_webhook_logs` with `gateway`, `event_type`, `signature_valid`, redacted `payload`, `processed_at`, and `processing_error` regardless of success or failure. The `payload` JSON MUST be redacted using the same PCI allowlist as `payment_attempts` (FR-PAY-003) before persistence. (Append-only — Constitution V)
- **FR-PAY-007**: System MUST treat webhook delivery as idempotent — replays of the same `gateway_ref` MUST NOT advance the payment past `captured` a second time and MUST NOT re-fire `PaymentCaptured`.
- **FR-PAY-008**: System MUST fire the `PaymentCaptured` (and `PaymentFailed`) domain event only after the database transaction commits, via `DB::afterCommit()` or a queued listener. (Constitution IX)
- **FR-PAY-009**: System MUST update `bookings.payment_status` to `paid` (or stay `pending` on failure) via a queued listener on `PaymentCaptured` — never inside the webhook transaction.
- **FR-PAY-010**: A scheduled job MUST sweep `payments` rows where `status = 'pending'` AND the parent booking's 24h payment hold has expired, transitioning them to `status = 'failed'` with `failure_code = 'expired_payment_hold'` and a translatable `failure_message`. Sweep is idempotent and runs at least every 15 minutes. Customer-facing impact: the customer may re-initiate payment on the same booking (subject to the booking's own state machine) which creates a new `payments` row.

**Refunds (Phase 4.1)**

- **FR-REF-001**: System MUST expose an admin Filament action on the Booking resource that delegates to `InitiateRefundAction` — Filament closures contain no business logic. The admin Filament action and `POST /api/v1/admin/bookings/{ulid}/refunds` are the **only** Phase 1 entry points for refunds; no customer-facing refund or self-cancel endpoint ships in this phase. (CLAUDE.md Filament rules + Tech Decisions §1)
- **FR-REF-002**: System MUST resolve refund eligibility via `RefundPolicyService::policyFor(ProductType $type)` returning a `RefundPolicy` value object. The service MUST use `match($enum)` — never `if/elseif` on type strings. (Constitution II)
- **FR-REF-003**: Rental refund policy MUST allow refunds only when every rental item's `event_starts_at` is at least `24 hours` in the future AND the item has not entered `setup` status. The 24h boundary is inclusive (`>= 24h` allowed). The cutoff value MUST be configurable via a per-service or `app_settings` override; default 24h.
- **FR-REF-004**: Sale refund policy MUST allow refunds only while the corresponding `booking_items.item_status` is strictly less than `in_preparation` in the sale fulfillment state machine.
- **FR-REF-005**: Digital refund policy MUST allow refunds when (a) the item is not yet `delivered`, OR (b) the item is `delivered` AND the linked `service_digital_details.is_refundable_after_delivery = true`.
- **FR-REF-006**: System MUST persist every refund in the `refunds` table with `payment_id`, `booking_id`, `amount_minor`, `amount_currency`, `reason_code`, `reason_notes` (translatable JSON, EN+AR), `gateway_ref`, `status`, and `initiated_by`. `reason_code` MUST be one of the fixed enum values: `customer_request`, `vendor_cancellation`, `service_unavailable`, `duplicate_charge`, `admin_discretion`. The Filament admin action presents these as a `Select` and the `InitiateRefundRequest` validates server-side via Laravel `Rule::in([...])`. Any other value is rejected with `422`. Free-text context goes into `reason_notes`. (Constitution III + IV)
- **FR-REF-007**: System MUST fire the `RefundCompleted` domain event after the gateway confirms the refund, after transaction commit, so Settlement (Phase 4.2) can later append a counter-entry to `wallet_ledger`. (Constitution IX)
- **FR-REF-008**: System MUST reject any refund attempt that fails the per-type policy with HTTP `422` and a translatable error message in the active locale. (Constitution IV)
- **FR-REF-009**: Phase 1 supports full refunds only — partial refund amounts are out of scope and MUST be rejected with `422` if requested. (Cut-list)

**Idempotency middleware (Phase 4.0)**

- **FR-IDEM-001**: System MUST provide an `idempotency` middleware that intercepts `Idempotency-Key` headers on payment-mutating endpoints, persists the response in `idempotency_keys` keyed by `(key, user_id, request_hash)`, replays the cached `response_status` + `response_body` on duplicate within `expires_at` (24h TTL), and returns `409 Conflict` when the same key is used with a different request body hash.
- **FR-IDEM-002**: Idempotency middleware MUST be applied to: `POST /api/v1/customer/bookings/{ulid}/payments`, `POST /api/v1/admin/bookings/{ulid}/refunds`. (Reused by Phase 4.2 for withdrawals.)
- **FR-IDEM-003**: A scheduled job MUST purge `idempotency_keys` rows where `expires_at < now()` daily.

**Cross-cutting**

- **FR-X-001**: Every API response from this module MUST follow the standard `{ data, meta, errors }` envelope. (CLAUDE.md §12)
- **FR-X-002**: Translatable fields (`reason_notes`, `failure_message`) MUST be JSON columns with both `en` and `ar` keys present; empty strings fail validation. (Constitution IV)
- **FR-X-003**: Every state transition MUST be captured by `audit_logs` (via `spatie/laravel-activitylog` + custom audit trail). (CLAUDE.md §10)
- **FR-X-004**: All money arithmetic MUST go through `Brick\Money\Money` with explicit rounding modes — no native float math. (Constitution III)

### Key Entities

- **Payment** — A single payment attempt on a booking. One booking = one payment (Phase 1 cut-list). State machine: `pending → authorized → captured` (success) or `pending → failed` (failure) or `captured → refunded / partially_refunded / voided` (recovery). Owns the gateway reference, the captured amount, and the link to the customer.
- **Payment Attempt** — Append-only forensic record of every outbound gateway call (initiate, status check). Used for debugging gateway flakiness; never read by domain logic.
- **Refund** — A reversal of a captured payment, initiated by an admin (Phase 1) with a translatable reason. Carries its own gateway reference because Paymob issues a separate refund transaction. State machine: `pending → processing → completed / failed`. One payment can have at most one refund row in Phase 1 (full refunds only).
- **Idempotency Key** — Short-lived (24h) cache of a request+response pair keyed by `(key, user_id, request_hash)`. Lets clients safely retry network-flaky payment calls without double-charging.
- **Gateway Webhook Log** — Append-only audit of every webhook delivery (signed or not). Used to debug gateway-side issues and to prove non-repudiation when Paymob disputes a transaction.
- **Refund Policy** — Value object returned by `RefundPolicyService::policyFor(ProductType)` carrying the per-type rule (`allowed: bool`, `reason_code`, `reason_message`). Resolved freshly on every refund attempt — never cached.

---

## Constitution Check *(mandatory)*

| # | Principle | Status | How this feature satisfies it |
|---|---|---|---|
| I | Modular Monolith — no microservices, no cross-module model imports | ✅ PASS | New `app/Modules/Payments/` follows the standard layout. Booking module subscribes via `PaymentCaptured` event; Settlement (Phase 4.2) will subscribe via `RefundCompleted`. The single outbound contract is `Domain/Contracts/PaymentGateway` (interface), implemented by `Infrastructure/Gateways/PaymobGateway`. ADR-0008 to be drafted. |
| II | Three Product Types — `match($enum)`, never if/elseif on strings | ✅ PASS | `RefundPolicyService::policyFor(ProductType)` uses `match` over the `ProductType` enum. Three test groups (`->group('rental')`, `->group('sale')`, `->group('digital')`) cover all three policy variants. |
| III | Money Discipline — integer minor units, `Brick\Money` | ✅ PASS | `payments.amount_minor`/`amount_currency` and `refunds.amount_minor`/`amount_currency` per Schema §6. All arithmetic via `Brick\Money\Money` with explicit rounding. No floats anywhere. `MoneyCast` reused from Shared module. |
| IV | Bilingual EN+AR — both locales mandatory | ✅ PASS | `refunds.reason_notes`, `payments.failure_message` are JSON translatable columns. Refund rejection messages returned in active locale via API Resource. Admin Filament action surfaces both EN/AR tabs for the reason field. |
| V | Append-Only Tables — no softDeletes, status-only updates | ✅ PASS | `payments` (status + `gateway_response_log` mutable, no soft delete), `payment_attempts` (fully immutable), `gateway_webhook_logs` (fully immutable per Schema §6). `refunds` has `status` updates only. `idempotency_keys` is short-lived but follows the same no-softDeletes rule. |
| VI | ADR Before Code | ⚠️ PENDING | New module → `ADR-0008-payments-module.md` MUST be drafted and accepted before `/speckit.plan` runs. Use `/new-module-adr Payments`. |
| VII | Test-First on Money/Auth/Bookings | ✅ PASS | Pest tests are required Day-1/Day-2 work for Phase 4.0 and Day-1 for Phase 4.1: webhook signature (valid + invalid), idempotency under concurrency, full payment flow, per-type refund windows (3 cases for rental, 2 for sale, 2 for digital). Coverage target ≥ 80% on Action classes. |
| VIII | Idempotency on State-Changing Endpoints | ✅ PASS | `POST /api/v1/customer/bookings/{ulid}/payments` and `POST /api/v1/admin/bookings/{ulid}/refunds` both go through the idempotency middleware backed by `idempotency_keys`. Concurrency test required. |
| IX | Domain Events Fire `DB::afterCommit` | ✅ PASS | `PaymentInitiated`, `PaymentCaptured`, `PaymentFailed`, `RefundCompleted` all dispatched via `DB::afterCommit()` or queued listeners. The Booking-side listener that updates `payment_status` runs on the queue, not synchronously. |
| X | Vendor Approval Two-Step Gate | N/A | This feature does not approve vendors. (Indirectly relevant: only services published by approved-for-type vendors are bookable, but that gate is enforced upstream in Catalog/Booking.) |
| XI | Document Storage — direct S3 vs MediaLibrary | N/A | This feature stores no uploaded documents. Webhook payloads are JSON in the database. |

**Locked stack compliance:** Paymob is the locked Phase 1 gateway (Constitution §"Locked Tech Stack"). No new packages are introduced — `idempotency_keys` is custom middleware backed by a table; HMAC verification uses native PHP `hash_hmac` + `hash_equals`. The `PaymentGateway` interface keeps the door open for Phase 2 GCC adapters (Tabby, Tamara) without changing the Action layer.

---

## API Endpoints

| Method | Endpoint | Auth | Roles | Idempotent | Form Request | Resource |
|---|---|---|---|---|---|---|
| `POST` | `/api/v1/customer/bookings/{ulid}/payments` | sanctum-cookie+token | `customer` | Yes (24h) | `InitiatePaymentRequest` | `PaymentResource` |
| `GET` | `/api/v1/customer/payments/{ulid}` | sanctum-cookie+token | `customer` (owner) | — | — | `PaymentResource` |
| `POST` | `/api/v1/webhooks/paymob` | none (HMAC-verified) | — | Yes (gateway-ref) | `PaymobWebhookRequest` | `{ "ok": true }` |
| `POST` | `/api/v1/admin/bookings/{ulid}/refunds` | sanctum-cookie+token | `admin` (with `payment.refund` permission) | Yes (24h) | `InitiateRefundRequest` | `RefundResource` |
| `GET` | `/api/v1/admin/refunds/{ulid}` | sanctum-cookie+token | `admin` | — | — | `RefundResource` |

All responses follow the `{ data, meta, errors }` envelope. Locale is set by `Accept-Language: en | ar` and respected at the Resource layer. All path params use `public_id` ULIDs, never internal `id`. Each endpoint MUST be added to `.specify/memory/api-registry.md` and a Bruno/Postman collection entry MUST be created in `docs/api/collections/` per the spec-template's API DOCUMENTATION CONSTRAINT.

**Scribe annotations required (per spec-template):**
- `@bodyParam` on every Form Request field with EN+AR example values where applicable.
- `@response` on every Resource with realistic example data including both `en` and `ar` keys for translatable fields.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A customer with a confirmed booking can complete payment end-to-end (initiate → Paymob hosted → webhook → captured) using a Paymob test card in under 90 seconds.
- **SC-002**: Webhook signature verification rejects 100% of payloads with tampered or missing signatures (verified by 10+ adversarial test cases).
- **SC-003**: Replaying any captured webhook 100 times in succession results in exactly one `payments.status = captured` row and exactly one `PaymentCaptured` event delivery to subscribers.
- **SC-004**: Concurrent initiate-payment requests with the same `Idempotency-Key` (10 parallel requests) result in exactly one `payments` row, one Paymob call, and one returned redirect URL — replayed 9 more times.
- **SC-005**: Each per-type refund policy is enforced correctly across all boundary cases: rental (`>24h` allowed, `=24h` allowed, `<24h` rejected, `setup` rejected), sale (`<in_preparation` allowed, `>=in_preparation` rejected), digital (`!delivered` allowed, `delivered + flag=true` allowed, `delivered + flag=false` rejected).

---

## Exit Criteria *(per `09_Phasing_Plan.md` lines 657–664 and 698–704)*

### Phase 4.0 — Paymob Gateway

- [ ] ✅ Test card succeeds in Paymob sandbox end-to-end (initiate → webhook → captured)
- [ ] ✅ Webhook updates both `payments.status` and `bookings.payment_status` after commit
- [ ] ✅ Bad-signature webhook rejected (`401`) and logged in `gateway_webhook_logs`
- [ ] ✅ Idempotency works under concurrent requests (proved by Pest concurrency test)

### Phase 4.1 — Refunds (per-type)

- [ ] ✅ Each product type's refund policy enforced correctly (Pest covers all 3 types: rental boundary, sale state, digital flag)
- [ ] ✅ Refund updates `payments.status` to `refunded` and (in Phase 4.2) will write a negative `wallet_ledger` entry
- [ ] ✅ Filament refund action delegates to `InitiateRefundAction` — no business logic in the closure

---

## Cut-List *(inherited from 09_Phasing_Plan.md Phases 4.0 + 4.1)*

**Phase 4.0:**
- ✂️ **Defer split payments** — exactly one `payments` row per booking in Phase 1. Schema supports multi-payment but the initiate Action enforces a single-payment invariant.
- ✂️ **Defer GCC adapters (Tabby, Tamara, HyperPay)** — Phase 2. `PaymentGateway` interface keeps the slot open without code changes.

**Phase 4.1:**
- ✂️ **Defer partial refunds** — full refund or none in Phase 1. Refund amount MUST equal `payments.amount_minor`.
- ✂️ **Defer automatic gateway-failure retry** — failed refunds remain in `failed` status for manual admin retry; no scheduled retry job in Phase 1.

If the phase runs over its 3-day budget, defer in this priority order: (1) automatic retry job, (2) Filament refund action UI polish, (3) digital `is_refundable_after_delivery` flag handling (fall back to "all digital refunds rejected after delivery" until Phase 1.5).

---

## Assumptions

- **Booking lifecycle prerequisite:** Phase 3.2 (Booking Negotiation) is complete and `bookings.lifecycle_status` can reach `confirmed` with `payment_status = pending`. This feature picks up at that handoff. (Project Index — Phase 3.2 just completed at commit `3447460`.)
- **Currency:** Phase 1 is EGP-only. Multi-currency activation is explicitly forbidden Phase 1 work (Constitution §"Phase 1 Forbidden Features"). Schema columns exist; runtime rejects mismatched currencies.
- **Paymob credentials:** Sandbox credentials are available in `.env` as `PAYMOB_API_KEY`, `PAYMOB_HMAC_SECRET`, `PAYMOB_INTEGRATION_ID`. Production credentials are rotated separately and never committed.
- **Sanctum config:** Customer endpoints work through both SPA cookies (Next.js, Phase 1.5) and Bearer tokens (Flutter mobile). Admin endpoints work through SPA cookies (Filament).
- **Booking item state machines:** The sale state machine includes `in_preparation` and the digital state machine includes `delivered` (per Tech Decisions §2.4 and Phase 2.2/2.3 deliverables). This feature reads but does not modify those state machines.
- **Filament admin permission `payment.refund`:** Will be seeded by `php artisan shield:generate --all` after the `RefundResource` (or refund Action on `BookingResource`) is created. Admins assigned this permission can issue refunds.
- **`bookings.payment_status` column** already exists from Phase 3.1 (Booking: Draft + Items) per Schema §5. The listener writes to it; this feature does not migrate it.
- **No platform-level fee added on top of Paymob:** Phase 1 charges the customer the booking total only. Gateway fees are absorbed by the platform until commission rules expand in Phase 4.2.
- **Webhook delivery URL:** `https://{domain}/api/v1/webhooks/paymob` configured in the Paymob dashboard. Local development uses ngrok or Cloudflare Tunnel pointed at the dev server.
- **HMAC algorithm:** Paymob uses HMAC-SHA512 over a sorted-key concatenation of specific transaction fields per Paymob's official documentation. The exact field list is implementation detail in `PaymobGateway` — not in this spec.

---

## Dependencies

**Inbound (this feature requires):**
- Phase 3.1 — `bookings`, `booking_items` tables + `payment_status` column
- Phase 3.2 — Booking can reach `confirmed` lifecycle status
- Phase 2.1 — `service_rental_details` + rental fulfillment state machine (`setup` state)
- Phase 2.2 — `service_sale_details` + sale fulfillment state machine (`in_preparation` state)
- Phase 2.3 — `service_digital_details.is_refundable_after_delivery` column + digital fulfillment state machine (`delivered` state)
- Shared `MoneyCast` (Phase 0.2) and `ProductType` enum (Phase 2.0)

**Outbound (this feature unblocks):**
- Phase 4.2 — Settlement subscribes to `PaymentCaptured` and `RefundCompleted` to write `wallet_ledger` rows
- Phase 5.0 — Communication subscribes to `PaymentCaptured` to send the customer's payment receipt notification
