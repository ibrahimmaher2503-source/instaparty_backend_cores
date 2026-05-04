# Phase 0 Research: Admin Booking Override

**Feature**: Phase 6.5 — Admin Booking Override
**Date**: 2026-05-03

---

## Decision 1: Booking State Machine — Existing States

**Decision**: Use the existing `LifecycleStatus` enum directly. No new lifecycle states are required for this phase.

**Findings**:
`App\Modules\Booking\Domain\Enums\LifecycleStatus` has:
`Draft`, `Submitted`, `VendorReview`, `CustomerReview`, `Confirmed`, `Active`, `Completed`, `Cancelled`

Force-cancel maps to `Cancelled`. Vendor timeout maps to `Cancelled` (from `VendorReview`).

**Rationale**: The `Cancelled` state already exists and has existing listeners (`ReleaseInventoryOnCancellationListener`). Re-using it avoids schema changes and preserves existing automation.

**Alternatives considered**: Adding a `AdminCancelled` state — rejected because it duplicates `Cancelled` semantics and would require new state machine transitions without benefit.

---

## Decision 2: VendorSubStatus for Vendor Timeout

**Decision**: Add a new `TimedOut` case to `VendorSubStatus` for the `booking_vendors` record of the non-responding vendor.

**Findings**:
`App\Modules\Booking\Domain\Enums\VendorSubStatus` currently has: `Pending`, `Accepted`, `Modified`, `Rejected`, `Cancelled`, `InProgress`, `Completed`.

There is no explicit "timed out" sub-status. Adding `TimedOut` is a backward-compatible enum extension.

**Rationale**: Distinguishes admin-triggered timeout (observable in the intervention log) from normal `Cancelled` sub-status.

**Alternatives considered**: Reusing `Rejected` or `Cancelled` for the vendor sub-status on timeout — rejected because both have different semantic meanings and would make the booking history ambiguous.

---

## Decision 3: RefundPolicyService Availability and Interface

**Decision**: `ForceCancelBookingAction` does NOT call `RefundPolicyService` directly. Instead it fires `BookingForceCancelled` event; the Payments module listener calls `InitiateRefundAction` internally.

**Findings**:
`RefundPolicyService` lives in `App\Modules\Payments\Application\Services\RefundPolicyService`. Its signature is:
```
policyFor(ProductType $productType, string $itemStatus, ?Carbon $eventStartsAt, ?int $serviceId = null): RefundPolicy
```
It is a Payments-module-internal service. Direct import by Booking module would violate Constitution Principle I.

**Rationale**: Domain events (Principle IX) are the correct cross-module communication mechanism. The Payments module already has the pattern via `OnBookingSubmitted` style listeners in Communication.

**Alternatives considered**: Exposing `RefundPolicyService` via a `Booking/Domain/Contracts/RefundInitiator` contract — viable but adds an extra abstraction layer when a domain event already achieves the same decoupling with less code.

---

## Decision 4: Audit Log Writing Pattern

**Decision**: Use `DB::table('audit_logs')->insert([...])` directly within the DB::transaction, matching the existing Settlement module pattern.

**Findings**:
Settlement module (`ApproveAndMarkWithdrawalPaidAction`, `RejectWithdrawalAction`, `ReverseCommissionAction`) all write to `audit_logs` via `DB::table('audit_logs')->insert([...])` inside `DB::transaction`.

**Rationale**: Consistent with existing pattern. `audit_logs` is append-only (no model needed for writes); reading it back uses the model if needed.

**Alternatives considered**: An `AuditLogger` service class — unnecessary abstraction given the simple insert pattern already established.

---

## Decision 5: booking_state_transitions Writing Pattern

**Decision**: Write `booking_state_transitions` via `BookingStateTransition::create([...])` inside the same `DB::transaction` as the status mutation.

**Findings**:
`WriteBookingStateTransitionListener` shows: `BookingStateTransition::create(['transitionable_type', 'transitionable_id', 'from_state', 'to_state', 'trigger_kind'])`. The `trigger_kind` for admin interventions will be `'admin'` (new value alongside existing `'system'`).

**Rationale**: Direct model create inside the transaction is the existing pattern. The new `trigger_kind = 'admin'` value self-documents the source.

---

## Decision 6: Notification Dispatch Pattern for Interventions

**Decision**: Fire domain events (`BookingForceCancelled`, `VendorResponseTimedOut`, `AlternativeVendorProposed`, `VendorProposalDecided`) after commit; Communication module adds queued listeners for each.

**Findings**:
`App\Modules\Communication\Domain\Contracts\NotificationDispatcher` exists (interface). `DispatchNotificationAction` implements it. Communication module already has booking event listeners (`OnBookingConfirmed`, `OnBookingSubmitted`, `OnBookingModified`). The pattern is: Booking event → Communication listener → `DispatchNotificationAction::execute(DispatchNotificationDTO)`.

**Rationale**: Follows existing cross-module notification pattern exactly.

---

## Decision 7: Customer Consent API Endpoint

**Decision**: `PATCH /api/v1/customer/bookings/{bookingPublicId}/vendor-proposals/{interventionPublicId}/respond` — a single endpoint accepting `{ "decision": "accepted" | "rejected" }`.

**Rationale**: RESTful resource action. The intervention's `public_id` (ULID) is exposed in the customer notification, enabling direct linking. PATCH is appropriate for partial update of consent status.

---

## Decision 8: Proposal Expiry Mechanism

**Decision**: Use the existing Laravel scheduler. A new `ExpireVendorProposalsCommand` runs hourly and marks `customer_consent_status = 'consent_expired'` on proposals where `consent_expires_at < now()`.

**Rationale**: Simple, reliable, no new infrastructure required. The existing `ReleaseExpiredReservationsCommand` in the Booking module establishes this pattern.

---

## Decision 9: ADR Number Conflict

**Decision**: Create `docs/adr/0013-admin-booking-override.md`. The phasing plan erroneously references ADR-0011, which is now taken by the reviews module.

**Findings**: ADR sequence in `docs/adr/`: 0001, 0003, 0004, 0005, 0009, 0010, 0011, 0012. Next available: 0013.

---

## Decision 10: Vendor Proposal — Intervention Table as Append-Only

**Decision**: `booking_admin_interventions` is mostly append-only but `customer_consent_status` IS mutable (like `withdrawal.status`). The table gets `created_at` AND `updated_at` to track when consent decisions were made.

**Rationale**: Constitution Principle V explicitly allows status-column updates on otherwise append-only tables. Including `updated_at` preserves the timestamp of consent decisions.
