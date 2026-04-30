# Feature Specification: Booking Negotiation Loop

**Feature Branch**: `006-booking-negotiation-loop`
**Created**: 2026-04-30
**Status**: Draft
**Phase**: 3.2 — Booking Negotiation (Week 4–5)

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Customer Submits Booking for Vendor Review (Priority: P1)

A customer with a draft booking containing items from one or more vendors submits it for vendor review. The system routes the submission to each vendor simultaneously, locking the booking from further edits until the vendor(s) respond. The vendor then accepts, modifies, or rejects their portion of the booking.

**Why this priority**: Nothing else in the negotiation loop can happen until a booking is submitted. This is the gateway action that transitions the booking from "shopping cart" to "in-negotiation."

**Independent Test**: Create a draft booking with items from two vendors, submit it, and verify both vendors receive a pending review task and the booking moves to `vendor_review` status. Tests pass with no modifications workflow implemented.

**Acceptance Scenarios**:

1. **Given** a customer with a draft booking with items from two vendors, **When** the customer submits the booking (with idempotency key), **Then** the booking `lifecycle_status` moves to `submitted` → `vendor_review`, each `booking_vendor.sub_status` moves to `pending`, a `response_deadline` is set (24h SLA), a `BookingSubmittedToVendor` event fires per vendor, and a `booking_state_transitions` row is appended.
2. **Given** a customer submits with a duplicate idempotency key within 24h, **When** the same submit request arrives again, **Then** the same idempotency response is returned without creating duplicate state transitions.
3. **Given** a booking that is not in `draft` status, **When** the customer attempts to submit it, **Then** a 409 Conflict is returned.
4. **Given** an unauthenticated request to submit, **Then** a 401 Unauthorized is returned.
5. **Given** a customer trying to submit another customer's booking, **Then** a 403 Forbidden is returned.

---

### User Story 2 — Vendor Accepts Their Portion (Priority: P2)

A vendor reviews their assigned items and accepts the booking as-is. Once all vendors for a booking have accepted, the booking moves to `confirmed` status automatically.

**Why this priority**: Vendor acceptance is the happy path — the most common outcome. Must work before the modification loop is useful.

**Independent Test**: Submit a booking to one vendor, have the vendor accept it, and verify the booking transitions to `confirmed`. No modification workflow needed.

**Acceptance Scenarios**:

1. **Given** a booking in `vendor_review` with `booking_vendor.sub_status = pending`, **When** the vendor calls the accept endpoint, **Then** `booking_vendor.sub_status` moves to `accepted`, `responded_at` is set, and a `booking_state_transitions` row is appended.
2. **Given** a multi-vendor booking where all vendors have accepted, **When** the last vendor accepts, **Then** the booking `lifecycle_status` moves to `confirmed` and `confirmed_at` is set.
3. **Given** a multi-vendor booking where one vendor is still pending, **When** the second vendor accepts, **Then** the booking remains in `vendor_review` (waiting for the pending vendor).
4. **Given** a vendor trying to accept a booking not assigned to them, **Then** a 403 Forbidden is returned.

---

### User Story 3 — Vendor Proposes Modifications (Priority: P2)

A vendor proposes changes to their line items — new price, revised slot, quantity change, additional surcharge, or a new item — with a translatable explanation. The customer is notified and sees a highlighted diff of proposed changes. The booking enters `customer_review` status.

**Why this priority**: Modification is the core of the negotiation loop. Accepting and rejecting without modification would make the loop trivial.

**Independent Test**: Submit a booking to a vendor, have the vendor propose a price change modification, and verify the booking moves to `customer_review`, a `booking_modifications` row is created with `diff_snapshot`, and the customer can view it.

**Acceptance Scenarios**:

1. **Given** a booking in `vendor_review` with a pending vendor, **When** the vendor submits a modification proposal (with `proposal_kind`, payload, and `vendor_explanation`), **Then** a `booking_modifications` row is created with `status = pending`, `diff_snapshot` is computed (before/after), the booking `lifecycle_status` moves to `customer_review`, `booking_vendor.sub_status` moves to `modified`, and a state transition is logged.
2. **Given** a vendor modifies a Rental item slot time, **When** the modification is proposed, **Then** the `diff_snapshot` captures both the old `effective_starts_at`/`effective_ends_at` and the proposed new values.
3. **Given** a vendor proposes adding a new line item, **When** the modification is saved, **Then** a `booking_modification_items` row with `change_kind = add` and `target_booking_item_id = NULL` is created.
4. **Given** a vendor proposes removing an existing line item, **Then** a `booking_modification_items` row with `change_kind = remove` is created referencing the target item.

---

### User Story 4 — Customer Reviews and Accepts/Rejects Modifications (Priority: P3)

The customer is presented with a highlighted diff of the vendor's proposed changes. The customer either accepts (applying the changes and moving forward) or rejects (reverting to the original and signalling the vendor to try again or withdraw).

**Why this priority**: Completes the loop. Without customer decision, negotiation stalls permanently.

**Independent Test**: With a modification in `customer_review`, have the customer accept it. Verify booking items reflect the changes, modification `status = customer_accepted`, and the booking advances toward `confirmed`.

**Acceptance Scenarios**:

1. **Given** a booking in `customer_review` with a pending modification, **When** the customer accepts the modification (with idempotency key), **Then** the modification `status` moves to `customer_accepted`, `customer_decision_at` is set, the `booking_items` are updated to reflect the modification payload, booking totals are recalculated, the booking `lifecycle_status` advances (to `vendor_review` if other vendors still pending, or `confirmed` if all resolved), and a state transition is logged.
2. **Given** a customer accepts a modification, **When** the modification adds a new item, **Then** a new `booking_items` row is created from the modification payload.
3. **Given** a customer rejects a modification, **When** the rejection is saved, **Then** the modification `status` moves to `customer_rejected`, the booking items revert to pre-modification state, the booking returns to `vendor_review`, and the vendor's `sub_status` reverts to `pending` for another attempt.
4. **Given** a customer trying to decide on a modification not belonging to their booking, **Then** a 403 Forbidden is returned.
5. **Given** the customer accepts a modification with a duplicate idempotency key within 24h, **Then** the idempotent response is returned without re-applying changes.

---

### User Story 5 — Vendor Rejects Their Portion (Priority: P3)

A vendor declines to fulfill their portion of the booking. The customer is notified. Phase 1 does not auto-replace the vendor — the customer decides what to do next.

**Why this priority**: Rejection is a boundary condition. Per FR-17 and FR-18, Phase 1 must support rejection without auto-replacement.

**Independent Test**: Submit a booking to one vendor, have the vendor reject it. Verify `booking_vendor.sub_status = rejected`, the booking moves to `customer_review`, and no auto-replacement occurs.

**Acceptance Scenarios**:

1. **Given** a booking in `vendor_review` with a pending vendor, **When** the vendor rejects their portion (with optional reason), **Then** `booking_vendor.sub_status` moves to `rejected`, `responded_at` is set, the booking `lifecycle_status` moves to `customer_review`, and a state transition is logged.
2. **Given** a vendor rejects, **When** the customer views the booking, **Then** the rejection reason (if provided) is visible and no alternative vendor is auto-assigned.
3. **Given** all vendors on a booking reject, **When** the last rejection is recorded, **Then** the booking `lifecycle_status` moves to `cancelled`.

---

### Edge Cases

- What happens when a vendor's 24h `response_deadline` passes without a response? (Phase 5.0 — expiration timers deferred per cut-list; deadline is stored but auto-escalation is not implemented in Phase 1)
- What happens when a modification's `expires_at` passes? (Deferred to Phase 5.0)
- What happens when a booking has items from 3 vendors and vendor 1 accepts, vendor 2 modifies, vendor 3 rejects — simultaneously? (Each vendor operates on their own `booking_vendor` row independently; booking lifecycle advances only when all vendors have responded)
- What happens when the customer tries to add/remove items while the booking is in `vendor_review` or `customer_review`? (Blocked — only `draft` status allows item edits)
- What if a vendor proposes multiple modifications in sequence? (Each modification is a separate `booking_modifications` row; only one per `booking_vendor` may have `status = pending` at a time)
- What happens when a booking with a Rental item is accepted — does the inventory reservation convert from `held` to `confirmed`? (Yes — on booking confirmation, `ServiceInventoryReservation` status transitions from `held` to `confirmed`)

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST allow a customer to submit a draft booking, transitioning it to `vendor_review` status and routing a task to each assigned vendor simultaneously.
- **FR-002**: The system MUST enforce idempotency on booking submission — duplicate submit requests with the same idempotency key within 24h MUST return the same response without duplicate side effects.
- **FR-003**: The system MUST set a 24-hour `response_deadline` on each `booking_vendor` row at submission time.
- **FR-004**: The system MUST allow a vendor to accept their assigned portion; when all vendors accept, the booking MUST transition to `confirmed`.
- **FR-005**: The system MUST allow a vendor to propose modifications to their line items, including: changing price, changing slot time, changing quantity, adding a surcharge, adding a new item, removing an item, and adding operational notes.
- **FR-006**: Every vendor modification MUST store a `diff_snapshot` containing the before and after values of changed fields, suitable for rendering a visual diff to the customer.
- **FR-007**: The system MUST move the booking to `customer_review` when a vendor proposes modifications, and MUST notify the customer that their review is required.
- **FR-008**: The system MUST allow a customer to accept a pending modification; acceptance MUST apply the modification payload to the affected `booking_items`, recalculate booking totals, and advance the booking status.
- **FR-009**: The system MUST allow a customer to reject a pending modification; rejection MUST revert the booking to `vendor_review` with the vendor's `sub_status` reset to `pending` for another attempt.
- **FR-010**: The system MUST enforce idempotency on customer modification acceptance — duplicate acceptance requests with the same idempotency key within 24h MUST return the same response.
- **FR-011**: The system MUST allow a vendor to reject their assigned portion; rejection MUST notify the customer and MUST NOT auto-assign an alternative vendor (FR-17).
- **FR-012**: When all vendors on a booking reject, the booking MUST transition to `cancelled`.
- **FR-013**: Every state transition on `Booking`, `BookingVendor`, and `BookingItem` MUST be appended to `booking_state_transitions` with the actor, trigger kind, and context.
- **FR-014**: The negotiation loop MUST support multiple rounds — a vendor may propose modifications and the customer may reject them repeatedly, without a hard cap in Phase 1.
- **FR-015**: The system MUST create a new `booking_snapshots` row (versioned read model) after each state-changing event in the negotiation lifecycle.
- **FR-016**: Admin users MUST be able to view a monitor page listing all bookings in `vendor_review` or `customer_review`, filtered by product type, showing vendor response deadlines (FR-16). Admin intervention beyond monitoring is deferred to Phase 7.0 (cut-list).
- **FR-017**: On booking confirmation, all `held` inventory reservations for rental items in the booking MUST be upgraded to `confirmed` status.

### Key Entities

- **Booking**: Aggregate root. `lifecycle_status` drives the negotiation state machine (`draft` → `submitted` → `vendor_review` ↔ `customer_review` → `confirmed` | `cancelled`).
- **BookingVendor**: Per-vendor sub-aggregate. `sub_status` tracks individual vendor response (`pending` → `accepted` | `modified` | `rejected`).
- **BookingModification**: A single vendor-proposed change proposal on a `booking_vendor`. Captures `proposal_kind`, `diff_snapshot`, `vendor_explanation`, and `status`. At most one `pending` modification per `booking_vendor` at a time.
- **BookingModificationItem**: A single line-item change within a modification (add, remove, update). `payload` carries the proposed new values.
- **BookingStateTransition**: Append-only audit trail of every state change on Booking, BookingVendor, or BookingItem.
- **BookingSnapshot**: Versioned append-only read model. A new snapshot is created after each negotiation event so the UI can diff across versions.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A customer can complete the full negotiation loop (submit → vendor modifies → customer accepts → confirmed) in a single automated test run covering all three product types (Rental, Sale, Digital).
- **SC-002**: Duplicate submit and confirm-modification requests with identical idempotency keys within 24h return the original response and produce no additional state transitions.
- **SC-003**: Every state change in the negotiation lifecycle (booking, booking_vendor, booking_item) produces exactly one `booking_state_transitions` row with a non-null `to_state` and `trigger_kind`.
- **SC-004**: The `diff_snapshot` on every `booking_modifications` row contains both the `before` and `after` values for all changed fields, sufficient for a frontend to render a highlighted diff without additional data fetching.
- **SC-005**: A booking with items from multiple vendors reaches `confirmed` only when every `booking_vendor` has `sub_status = accepted`; partial acceptance alone does not confirm the booking.
- **SC-006**: Admin monitor page renders all bookings in `vendor_review` or `customer_review` with per-type filter applied, response deadline visible, with no page load errors.
- **SC-007**: When a vendor rejects, the booking does not auto-assign an alternative vendor under any circumstance.

---

## Assumptions

- Modification expiration timers (`expires_at` enforcement) are deferred to Phase 5.0 — the column is stored but no automatic expiry action runs in Phase 1.
- Admin intervention workflow (facilitating vendor replacement, forcing decisions) is deferred to Phase 7.0. Phase 1 provides monitor-only Filament pages.
- Customer notifications (push/email/WhatsApp) on vendor response fire via the `BookingSubmittedToVendor`, `VendorModificationProposed`, `VendorAccepted`, `VendorRejected`, and `CustomerConfirmationNeeded` events — but the actual notification dispatch belongs to Phase 5.0 (Communication module). Events are fired; listeners may be stubs in Phase 1.
- Only one `booking_modifications` row per `booking_vendor` may have `status = pending` at a time. If the vendor wants to revise their proposal, they must withdraw the existing one first.
- Booking item edits (add/remove items) are blocked once the booking leaves `draft` status.
- Inventory reservations for Rental items transition from `held` → `confirmed` when the booking reaches `confirmed`. They transition to `released` if the booking is `cancelled`.
- The Filament BookingsMonitor page is view-only in Phase 1 — admin cannot intervene programmatically.
- `booking_state_transitions.trigger_kind` uses the `user` value for customer/vendor actions and `system` for automated state propagations.
- All three product types (Rental, Sale, Digital) go through the same negotiation loop. The only type-specific behavior is that Rental items carry slot reservation upgrades on confirmation.
