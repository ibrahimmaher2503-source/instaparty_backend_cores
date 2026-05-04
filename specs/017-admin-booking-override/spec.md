---
## REQUIRED CONTEXT (load before executing this command)

Before generating any artifact, you MUST silently read these files in order:
1. .specify/memory/constitution.md
2. .specify/memory/project-index.md
3. .specify/memory/api-registry.md
4. docs/specs/01_PRD.md
5. docs/specs/09_Phasing_Plan.md
6. docs/specs/11_DB_Schema.md
7. docs/specs/10_Package_List.md

CONSTRAINTS (non-negotiable):
- Every generated artifact must cite specific FR numbers from 01_PRD.md
- Every generated artifact must cite specific table names from 11_DB_Schema.md
- Every generated artifact must align to a Phase ID from 09_Phasing_Plan.md
- Never suggest a package not in 10_Package_List.md
- Never suggest a Phase 2 feature
- Never contradict docs/specs/02_Tech_Decisions.md locked stack

API DOCUMENTATION CONSTRAINT:
- Every API endpoint generated must include:
  - @bodyParam PHPDoc on every Form Request field (Scribe-compatible)
  - @response PHPDoc with realistic EN+AR example data on every Resource
  - Entry added to .specify/memory/api-registry.md
  - Bruno/Postman collection entry in docs/api/collections/
---

# Feature Specification: Admin Booking Override

**Feature Branch**: `017-admin-booking-override`
**Phase**: Phase 6.5 — Admin Booking Override (2 days, Week 7)
**Created**: 2026-05-03
**Status**: Draft
**PRD Coverage**: FR-16, FR-17, FR-18
**Tables Touched**: `bookings` (status updates), `booking_state_transitions` (audit), `booking_admin_interventions` (NEW)
**ADR Required**: `docs/adr/0013-admin-booking-override.md`

> **ADR Numbering Note**: The phasing plan references ADR-0011, but that number is now taken by `0011-reviews-module.md`. The correct ADR is `0013-admin-booking-override.md` (next available in sequence after `0012-loyalty-module.md`).

---

## Overview

Admins need a controlled way to intervene in bookings that have become stuck — either because a vendor has not responded within the SLA window, or because a booking has reached an unresolvable state and requires manual resolution. All interventions must be fully audited, preserve the customer's right to choose any alternatives (FR-17, FR-18), and be recorded in three places: the intervention log, the booking state transition history, and the platform audit log.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Admin Force-Cancels a Stuck Booking (Priority: P1)

An admin reviews a booking that cannot proceed (e.g., the vendor has gone inactive, the event date is imminent, and the customer cannot be left waiting). The admin force-cancels the booking, triggering the appropriate refund for the customer based on the product type and cancellation policy.

**Why this priority**: This is the most critical rescue path for stuck bookings. Customers with upcoming events need fast resolution, and an unresolved booking creates financial and reputational risk for the platform.

**Independent Test**: Can be tested by creating a stuck booking in `vendor_review` lifecycle state and asserting that after admin force-cancel: the booking's `lifecycle_status` becomes `cancelled`, a refund record is created, and the intervention is logged in `booking_admin_interventions`, `booking_state_transitions`, and `audit_logs`.

**Acceptance Scenarios**:

1. **Given** a booking with `lifecycle_status = 'vendor_review'` and an admin with the `force_cancel_booking` permission, **When** the admin force-cancels the booking with a mandatory reason text, **Then** the booking's `lifecycle_status` becomes `cancelled`, a refund is initiated matching the product type's refund policy, a `booking_admin_interventions` row is inserted with `intervention_type = 'force_cancel'` and `before_state`/`after_state` captured, a `booking_state_transitions` row is appended, and an `audit_logs` row is appended.

2. **Given** a booking with `lifecycle_status = 'confirmed'` (past the vendor response phase), **When** the admin attempts force-cancel without providing a reason, **Then** the action is rejected with a validation error requiring the reason field.

3. **Given** a force-cancel has been completed, **When** the customer views the booking, **Then** the booking shows `cancelled` status and a refund notification has been dispatched to the customer.

4. **Given** a booking with product type `rental`, **When** admin force-cancels, **Then** the refund amount follows the rental refund policy (configurable window relative to `event_starts_at`). For `sale`, the refund follows the sale policy. For `digital`, the refund follows the digital policy (respects `is_refundable_after_delivery`).

---

### User Story 2 — Admin Forces Vendor Response Timeout (Priority: P2)

A vendor has not responded to a booking request within the platform's SLA deadline. Instead of waiting for the automated timeout job, an admin manually triggers the timeout — moving the booking to the appropriate "vendor non-response" state so the customer can take action sooner.

**Why this priority**: Automated timeout jobs may have delays. Admin-triggered timeout ensures customer-facing SLAs are met when a vendor is known to be unresponsive.

**Independent Test**: Can be tested by creating a booking in `vendor_review` state and asserting that after admin-triggered timeout: the booking's `lifecycle_status` transitions to `cancelled` (or `customer_review` depending on configuration), the `booking_vendors` sub-status reflects the timeout, an intervention record is logged, and the customer receives a notification.

**Acceptance Scenarios**:

1. **Given** a booking in `lifecycle_status = 'vendor_review'` with a vendor who has not responded, **When** the admin triggers vendor response timeout with a reason, **Then** the booking transitions to `cancelled` (or back to `customer_review` to allow re-selection), the `booking_vendors` record for the unresponsive vendor is marked as timed-out, all three audit tables are updated, and the customer receives a notification about the vendor's non-response.

2. **Given** a booking NOT in `vendor_review` state, **When** admin attempts to trigger vendor timeout, **Then** the action is rejected with an error stating the booking is not in a state where vendor timeout applies.

3. **Given** a vendor timeout is triggered, **When** the admin views the intervention log for the booking, **Then** the intervention shows `intervention_type = 'vendor_timeout'` with the admin's identity, timestamp, reason, `before_state`, and `after_state`.

---

### User Story 3 — Admin Proposes Alternative Vendor to Customer (Priority: P3)

When the original vendor cannot fulfill a booking, the admin identifies an alternative vendor who can and proposes this to the customer. The customer retains full decision-making authority — the system does not auto-switch vendors (FR-17, FR-18).

**Why this priority**: Serves customer retention when the original vendor fails, but must not bypass customer agency. FR-17 and FR-18 are hard constraints from the PRD.

**Independent Test**: Can be tested by creating a booking with a failed vendor and asserting that after admin proposal: a new `booking_admin_interventions` row with `intervention_type = 'vendor_proposal'` and `customer_consent_status = 'pending'` is created, the original booking's state is unchanged, and the customer receives a notification inviting them to accept or reject.

**Acceptance Scenarios**:

1. **Given** a booking where the original vendor cannot fulfill and an admin selects a replacement vendor from the platform, **When** the admin submits a vendor proposal with the proposed vendor's ID and a reason, **Then** the intervention is logged with `intervention_type = 'vendor_proposal'`, `customer_consent_status = 'pending'`, the booking's lifecycle state is NOT changed, a notification is sent to the customer presenting the alternative, and the customer can access an API endpoint to accept or reject the proposal.

2. **Given** a pending vendor proposal exists for a booking, **When** the customer accepts the proposal via the API, **Then** the `customer_consent_status` on the intervention becomes `accepted`, the booking continues (vendoring and confirmation flow resumes with the new vendor), and the intervention + booking state transition logs are updated.

3. **Given** a pending vendor proposal exists, **When** the customer rejects the proposal, **Then** `customer_consent_status` becomes `rejected`, the booking state is unchanged, and the customer is presented with the option to cancel or wait.

4. **Given** an admin attempts to submit a vendor proposal for a booking that already has a `pending` proposal, **Then** the action is rejected — only one pending proposal at a time per booking.

5. **Given** an admin proposes a vendor, **When** viewed from the admin panel, **Then** the system must NOT have auto-replaced the original vendor — the original vendor record in `booking_vendors` remains until the customer accepts.

---

### User Story 4 — Admin Adds Internal Note to Booking (Priority: P4)

An admin adds a text note to a booking for internal tracking — e.g., "contacted vendor by phone, awaiting callback" — without altering any booking state.

**Why this priority**: Lowest risk, highest utility for customer support workflows. Enables admin team coordination without triggering side effects.

**Independent Test**: Can be tested by posting a note and verifying: a `booking_admin_interventions` row with `intervention_type = 'admin_note'` is created, the booking's lifecycle/payment/fulfillment statuses are unchanged, and the note is visible in the admin booking detail view.

**Acceptance Scenarios**:

1. **Given** any booking in any state, **When** an admin submits a note with a non-empty reason/body, **Then** a `booking_admin_interventions` row is inserted with `intervention_type = 'admin_note'`, `before_state` and `after_state` are identical (no transition), and the note body is stored. The booking statuses are not mutated.

2. **Given** an admin submits a blank note, **Then** validation rejects it with a field-required error.

3. **Given** notes exist on a booking, **When** the admin views the booking detail in Filament, **Then** all intervention records (across all types) are listed in reverse-chronological order.

---

### Edge Cases

- What happens when a force-cancel is triggered on a booking whose payment has already been fully refunded? System should skip refund initiation and record reason.
- What happens when an admin triggers vendor timeout on a booking that was already auto-timed-out by the scheduler? System should detect the booking is not in `vendor_review` state and reject the action.
- What happens if a customer never responds to a vendor proposal (pending consent)? The proposal expires after a configurable TTL (defaulting to 48 hours) and the intervention is marked `consent_expired`. The customer receives a reminder notification at the midpoint.
- What happens when admin force-cancels a multi-vendor booking (multiple `booking_vendors`)? Each vendor's sub-status is transitioned; refund covers all `booking_items`.
- What happens if the proposed alternative vendor is not approved for the product type of the booking item? The action is rejected — the proposed vendor must be approved for the same product type.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-ADM-001**: System MUST allow admins with `force_cancel_booking` permission to cancel any booking regardless of its current `lifecycle_status`, provided a non-empty reason is supplied.
- **FR-ADM-002**: System MUST initiate a refund matching the product-type-specific refund policy when a booking is force-cancelled (rental policy, sale policy, or digital policy — using `match($productType)` dispatch).
- **FR-ADM-003**: System MUST allow admins with `timeout_vendor_response` permission to manually trigger a vendor response timeout on any booking in `vendor_review` state.
- **FR-ADM-004**: System MUST allow admins with `propose_alternative_vendor` permission to propose a replacement vendor to the customer — the proposal must NOT auto-replace the original vendor (FR-17 hard constraint).
- **FR-ADM-005**: System MUST enforce that the proposed replacement vendor is approved for the same product type as the booking items they would fulfill.
- **FR-ADM-006**: System MUST allow the customer to accept or reject a vendor proposal via an authenticated API endpoint; admin cannot confirm on the customer's behalf.
- **FR-ADM-007**: System MUST allow admins with `add_booking_note` permission to add internal notes to any booking without mutating booking statuses.
- **FR-ADM-008**: System MUST record every intervention in `booking_admin_interventions` capturing: `booking_id`, `admin_id`, `intervention_type`, `reason`, `before_state` (JSON snapshot), `after_state` (JSON snapshot), `customer_consent_status`.
- **FR-ADM-009**: System MUST append a row to `booking_state_transitions` for every intervention that mutates booking state (force_cancel, vendor_timeout) — not for admin_note or pending proposals.
- **FR-ADM-010**: System MUST append a row to `audit_logs` for every intervention without exception (all four types).
- **FR-ADM-011**: System MUST restrict all intervention actions to users with the `super_admin` or `booking_manager` role — unauthenticated requests return 401, insufficiently authorized requests return 403.
- **FR-ADM-012**: System MUST send a customer notification when: (a) a force-cancel completes, (b) a vendor timeout completes, (c) a vendor proposal is created, (d) a proposal nears expiry (configurable reminder window, default 24 hours before TTL).
- **FR-ADM-013**: System MUST enforce a single pending vendor proposal per booking — a second proposal while one is `pending` must be rejected.
- **FR-ADM-014**: System MUST expire pending vendor proposals after a configurable TTL (default 48 hours) and mark `customer_consent_status = 'consent_expired'`.

### Key Entities *(include if feature involves data)*

- **Admin Intervention** (`booking_admin_interventions`): An immutable record of a deliberate admin action on a booking. Captures who did what, when, why, the state before, the state after, and (for proposals) the customer's consent status. This is a NEW table — not in the locked 60-table schema but explicitly approved in Phase 6.5 scope.
- **Booking** (`bookings`): Existing entity. Phase 6.5 mutates `lifecycle_status` (for force-cancel and vendor-timeout actions) without adding new columns.
- **Booking State Transition** (`booking_state_transitions`): Existing append-only table. Receives one new entry per state-mutating intervention.
- **Audit Log** (`audit_logs`): Existing append-only table. Receives one new entry per intervention of any type.
- **Intervention Type** (ENUM): `force_cancel`, `vendor_timeout`, `vendor_proposal`, `admin_note` — governs which side effects fire and what state changes are permitted.
- **Customer Consent Status** (ENUM, nullable for non-proposal interventions): `pending`, `accepted`, `rejected`, `consent_expired` — relevant only for `vendor_proposal` interventions.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Every stuck booking in `vendor_review` state can be resolved by an admin within 2 minutes of identifying the issue, without requiring engineering team involvement.
- **SC-002**: 100% of admin interventions are traceable in all three audit surfaces (`booking_admin_interventions`, `booking_state_transitions` where applicable, `audit_logs`) — zero silent state mutations.
- **SC-003**: Zero cases of automated vendor replacement — customers must explicitly accept any alternative vendor before the booking proceeds with that vendor (FR-17 + FR-18 compliance verified by test suite).
- **SC-004**: Customer receives a notification within the notification dispatch window for each intervention that affects their booking (force-cancel, vendor timeout, proposal creation, proposal expiry reminder).
- **SC-005**: Admin intervention panel is accessible only to users with `super_admin` or `booking_manager` role — all other roles receive 403.
- **SC-006**: Force-cancel refund amount matches the product-type-specific policy with no manual override required — the correct policy is applied automatically for all three product types.
- **SC-007**: Pending vendor proposals that are not acted on expire automatically within the configured TTL (default 48h) without requiring manual cleanup.

---

## Phase Metadata

**Phase ID**: Phase 6.5 (Week 7, 2 days)
**Blocks**: None
**Blocked by**: Phase 3.x (Booking core + state machines — required for lifecycle_status to exist)

**Cut-list (if behind schedule)**:
- Defer proposal expiry TTL automation (make it a manual admin "expire" action for now)
- Defer multi-vendor booking force-cancel edge case handling
- Defer the customer proposal reminder notification (keep the initial proposal notification only)

**Exit Criteria**:
- ✅ Admin can resolve a stuck booking via Filament without code changes
- ✅ FR-17 enforced — no automated vendor replacement under any code path
- ✅ Every intervention triple-logged (intervention table + state transitions + audit_logs)
- ✅ Force-cancel refund applies the correct policy for all three product types
- ✅ Customer notification dispatched for every state-mutating intervention

---

## Constitution Check

| Principle | Status | Notes |
|---|---|---|
| I. Modular Monolith | ✅ PASS | All new code lives in `app/Modules/Booking/`; no cross-module model imports |
| II. Three Product Types — `match($enum)` | ✅ PASS | Force-cancel refund resolution uses `match($productType)` via `RefundPolicyService->policyFor()` |
| III. Money Discipline | ✅ PASS | Refund amounts flow through existing `Brick\Money` infrastructure; no new money columns in this phase |
| IV. Bilingual EN+AR | ✅ PASS | `reason` stored as plain text (admin-internal); customer-facing notification templates are translatable |
| V. Append-Only Tables | ✅ PASS | `booking_state_transitions` and `audit_logs` are append-only — no updates; `booking_admin_interventions` is also append-only by design (no UPDATE except `customer_consent_status` on proposals) |
| VI. ADR Before Code | ✅ PASS | ADR-0013 required before migrations are written |
| VII. Test-First Critical Paths | ✅ PASS | Day 2 explicitly covers all 4 intervention types in Pest; type-aware tests for all 3 product types on force-cancel |
| VIII. Idempotency | ⚠️ REVIEW | Admin intervention endpoints are state-mutating; `Idempotency-Key` header should be evaluated for `POST /admin/bookings/{id}/interventions` |
| IX. Domain Events `DB::afterCommit` | ✅ PASS | Notification dispatch and audit events fire after transaction commit |
| X. Vendor Approval Two-Step Gate | ✅ PASS | Proposed alternative vendor checked for product-type approval before proposal is accepted |
| XI. Document Storage | ➖ N/A | No file uploads in this feature |

---

## Bilingual Content Notes

- `reason` field on `booking_admin_interventions` is an internal admin note — stored as plain `TEXT`, English only is acceptable.
- Customer-facing notifications dispatched by this feature (force-cancel, vendor timeout, vendor proposal, proposal reminder) use existing `notification_templates` with translatable `subject` (JSON) and `body` (JSON) columns — EN+AR required on all four notification templates.
- Filament intervention panel labels (button text, column headers, form labels) must be registered in `Resources/lang/en/booking.php` and `Resources/lang/ar/booking.php`.
- API endpoint response fields visible to the customer (intervention type labels, consent status labels) must be translated at the API Resource layer before returning.

---

## Assumptions

- The booking module's state machine (`spatie/laravel-model-states`) already exists with `lifecycle_status` transitions from Phase 3.x — this feature adds admin-triggered transitions on top of existing machine.
- `RefundPolicyService->policyFor($productType)` (introduced in Phase 4.1) is available and handles rental, sale, and digital refund calculations — Phase 6.5 calls it rather than re-implementing.
- The `booking_admin_interventions` table is a new addition to the schema, not present in the locked 60-table spec — its introduction is scoped to Phase 6.5 and requires ADR-0013.
- Vendor proposal acceptance triggers a re-entry into the existing booking confirmation flow (Phase 3.x) — Phase 6.5 does not re-implement that flow, only the hand-off point.
- Admin `super_admin` and `booking_manager` roles already exist in `spatie/laravel-permission` — Phase 6.5 adds new permissions (`force_cancel_booking`, `timeout_vendor_response`, `propose_alternative_vendor`, `add_booking_note`) to these roles via Shield.
- Customer notification channels (push, email) are already operational from Phase 5.0 (Communication module) — Phase 6.5 adds new event keys and templates to the existing system.
- Multi-currency is out of scope (Phase 2) — all refunds in EGP only.
