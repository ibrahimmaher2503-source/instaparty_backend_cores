# Feature Specification: Booking — Draft Creation & Item Management

**Feature Branch**: `005-booking-draft-items`
**Created**: 2026-04-30
**Status**: Draft
**Phase**: 3.1 — Week 4
**PRD Coverage**: FR-1 through FR-9

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Create a Draft Booking (Priority: P1)

An authenticated customer decides to book services for a birthday party. They initiate a booking by providing basic event details (date, city, address snapshot). The system creates a draft booking in `lifecycle_status = draft` and returns a booking reference for the customer to continue adding items.

**Why this priority**: All other booking interactions depend on an existing draft. This is the entry point.

**Independent Test**: An authenticated customer can create a draft booking and receive back a booking `public_id` that can be used in subsequent requests.

**Acceptance Scenarios**:

1. **Given** an authenticated customer with a valid address, **When** they submit a create-booking request with an event date and delivery address, **Then** a draft booking is created with `lifecycle_status = draft`, `payment_status = unpaid`, and `fulfillment_status = pending`.
2. **Given** an unauthenticated request, **When** the create-booking endpoint is called, **Then** a 401 Unauthorized response is returned.
3. **Given** missing required fields (e.g., no event date), **When** the request is submitted, **Then** a 422 Unprocessable Entity response with field-level errors is returned.

---

### User Story 2 — Add Items from Multiple Vendors (Priority: P1)

The customer adds two inflatable rentals from Vendor A and one custom cake from Vendor B to their draft booking. The system reserves inventory for each item for 15 minutes and creates separate `booking_vendor` rows for Vendor A and Vendor B, each with their own subtotal and delivery fee.

**Why this priority**: Multi-vendor multi-type item addition is the core booking value proposition and the most complex flow.

**Independent Test**: Adding items from two distinct vendors to one booking produces two `booking_vendor` rows with correct subtotals, and inventory is reserved.

**Acceptance Scenarios**:

1. **Given** a draft booking and a published rental service, **When** the customer adds it, **Then** a `booking_item` row is created, inventory is reserved for 15 minutes, and the `booking_vendor` subtotal is updated.
2. **Given** items from two different vendors already in the basket, **When** the customer views the booking, **Then** two `booking_vendor` rows exist, each with its own subtotal and delivery fee.
3. **Given** a published sale service, **When** it is added, **Then** it uses the sale fulfillment state machine (distinct from rental).
4. **Given** a published digital service, **When** it is added, **Then** it uses the digital fulfillment state machine (distinct from rental and sale).
5. **Given** the customer adds a service and the 15-minute reservation hold expires without payment, **When** the hold expires, **Then** the reservation is released and inventory becomes available again.
6. **Given** a customer tries to add a service that is out of stock (no available inventory), **When** the request is submitted, **Then** a 409 Conflict response is returned.

---

### User Story 3 — Remove an Item from the Draft Booking (Priority: P2)

The customer changes their mind and removes the custom cake from their booking. The system releases the 15-minute inventory reservation for that item and recalculates the booking totals. If removing the item means Vendor B has no remaining items, the `booking_vendor` row for Vendor B is also removed.

**Why this priority**: Item removal is necessary for a usable basket UX, but the booking can exist without it in an MVP.

**Independent Test**: Removing a `booking_item` releases its reservation and recalculates the `booking_vendor` subtotal; if no items remain for a vendor, that vendor row is removed.

**Acceptance Scenarios**:

1. **Given** a draft booking with a sale item from Vendor B, **When** the customer removes it, **Then** the `booking_item` is deleted, the inventory reservation is released, and the Vendor B `booking_vendor` row is removed.
2. **Given** a draft booking with two rental items from Vendor A, **When** the customer removes one, **Then** the remaining item stays, Vendor A's subtotal is recalculated, and the Vendor A row persists.
3. **Given** an item not belonging to the customer's booking, **When** the customer tries to remove it, **Then** a 403 Forbidden response is returned.

---

### User Story 4 — View Per-Vendor Cost Breakdown (Priority: P2)

The customer views their draft booking and sees a breakdown: Vendor A — 2 rental items at X EGP + Y EGP delivery; Vendor B — 1 cake at Z EGP + W EGP delivery. Grand total is the sum of all vendor subtotals and delivery fees.

**Why this priority**: Transparent pricing per vendor is a key trust signal. Customers need this before confirming payment.

**Independent Test**: The booking detail response includes per-vendor cost breakdown that sums correctly to the grand total.

**Acceptance Scenarios**:

1. **Given** a booking with items from two vendors, **When** the customer retrieves the booking, **Then** the response includes a per-vendor array with `subtotal_minor`, `delivery_fee_minor`, and a grand `total_minor`.
2. **Given** an item is added or removed, **When** the customer retrieves the booking, **Then** totals reflect the current items.

---

### Edge Cases

- What happens when a customer tries to add an item to a booking that is no longer in `draft` status? → 409 Conflict with a clear message.
- What happens when inventory is reserved but the Meilisearch index is stale (service still appears in search but is now sold out)? → The `AddItemToBookingAction` checks live DB inventory, not the search index; returns 409 if unavailable.
- What happens when two customers simultaneously attempt to reserve the last unit of a rental service? → Pessimistic lock via `booking_locks` ensures only one succeeds; the other receives a 409.
- What happens when a digital service (unlimited inventory) is added? → No inventory reservation is needed; the digital state machine starts immediately.
- What happens when the customer's session expires during item addition? → 401 response; draft booking persists and can be resumed after re-authentication.
- What happens when a booking has no items (empty draft)? → Valid state; cannot proceed to checkout until at least one item is present.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST allow authenticated customers to create a draft booking with an event date and delivery address snapshot.
- **FR-002**: System MUST allow customers to add items from multiple vendors to a single draft booking.
- **FR-003**: System MUST create exactly one `booking_vendor` row per distinct vendor per booking; adding a second item from the same vendor updates the existing row.
- **FR-004**: System MUST place a 15-minute inventory reservation hold when a rental or sale item is added.
- **FR-005**: System MUST release the reservation hold when the corresponding item is removed from the booking.
- **FR-006**: System MUST automatically expire reservation holds after 15 minutes if the booking has not progressed to checkout.
- **FR-007**: System MUST calculate `subtotal_minor` and `delivery_fee_minor` per vendor and a grand `total_minor` across the entire booking.
- **FR-008**: System MUST support all three product types (rental, sale, digital) coexisting in a single booking.
- **FR-009**: System MUST apply a distinct fulfillment state machine to each `booking_item` based on its `product_type`.
- **FR-010**: System MUST use a pessimistic lock (`booking_locks`) to prevent overselling the last unit of a rental or sale service.
- **FR-011**: System MUST allow customers to remove items from a draft booking; removing the last item for a vendor removes that vendor's booking row.
- **FR-012**: Unauthenticated requests to all booking endpoints MUST return 401.
- **FR-013**: Attempts to modify a booking that is not in `draft` lifecycle status MUST return 409.
- **FR-014**: The delivery address MUST be stored as a snapshot (copied fields) on `booking_addresses`, not as a foreign key to `customer_addresses`.

### Key Entities

- **Booking**: The top-level order record. Tracks three status dimensions: `lifecycle_status` (draft → confirmed → …), `payment_status` (unpaid → paid → …), `fulfillment_status` (pending → in_progress → …).
- **Booking Address**: An address snapshot attached to a booking. Immutable after creation; not a live FK to customer addresses.
- **Booking Vendor**: A sub-record grouping all items from one vendor within one booking. Holds vendor-level subtotal and delivery fee.
- **Booking Item**: One line item within a booking. References a service (by snapshot), carries `product_type`, `quantity`, `unit_price_minor`, and `item_status` (per-type state machine).
- **Booking Lock**: A pessimistic lock record preventing concurrent reservation of the same resource. Unique on `(resource_type, resource_id, released_at)`.
- **Booking Snapshot**: An append-only versioned read model of the booking at each state transition. Phase 3.1 uses only the latest snapshot.
- **Service Inventory Reservation**: A timed hold on service inventory (15-minute TTL for cart holds).

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A customer can add items from 2 or more vendors to a single draft booking within one session.
- **SC-002**: Inventory reservation holds expire automatically within 15 minutes ± 30 seconds with no manual intervention.
- **SC-003**: Per-vendor subtotal and delivery fee calculations are accurate to the minor unit (no rounding errors).
- **SC-004**: All three product types (rental, sale, digital) can coexist in a single booking and each triggers its own distinct fulfillment lifecycle.
- **SC-005**: Two simultaneous customers competing for the last unit of a rental service results in exactly one successful reservation and one 409 response.
- **SC-006**: Removing an item from a booking is reflected immediately in the booking total and vendor breakdown on the next read.

---

## Assumptions

- The Catalog module (Phase 2.0) is complete: `services`, `service_rental_details`, `service_sale_details`, `service_digital_details`, and `service_inventory_reservations` tables exist with published services.
- The Identity module (Phase 1.0) is complete: authenticated customers have a `customer_profile` record.
- Delivery fee per vendor is a flat value stored on the vendor profile or service record; complex delivery fee calculation (distance-based, tiered) is deferred to a later phase.
- Base price × quantity is used for item total in Phase 3.1; pricing tiers are deferred (cut-list).
- `booking_snapshots` versioning (append one per state change) is partially deferred: Phase 3.1 writes one snapshot at draft creation. Full versioning is Phase 3.2+.
- Digital services have unlimited inventory; no reservation hold or lock is needed for them.
- Customers have one active basket/draft at a time (no multi-draft UX). If a prior draft exists, it is reused or must be discarded before creating a new one.
- Reservation expiry is handled by a scheduled job or queue worker; the booking endpoint does not expire holds synchronously.
- The `booking_customer_notes` table is migrated and schema-ready in Phase 3.1; the API endpoint for adding notes may be Phase 3.2.
