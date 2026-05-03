# Research: Booking Negotiation Loop

**Date**: 2026-04-30
**Phase**: 3.2 — Phase 0 Research

---

## Decision 1: Diff Snapshot Strategy

**Question**: How should `diff_snapshot` on `booking_modifications` be computed and structured?

**Decision**: Compute `diff_snapshot` as a JSON object with `before` and `after` keys at the `BookingModification` level, populated at the time `VendorModifyBookingAction` runs (inside the transaction). The before state is read directly from the current `booking_items` / `booking_vendor` rows; the after state is the vendor's proposed payload.

```json
{
  "before": {
    "items": [
      { "id": "01J...", "unit_price_minor": 50000, "quantity": 2, "effective_starts_at": "2026-06-01T10:00:00Z" }
    ],
    "subtotal_minor": 100000
  },
  "after": {
    "items": [
      { "id": "01J...", "unit_price_minor": 60000, "quantity": 2, "effective_starts_at": "2026-06-01T10:00:00Z" }
    ],
    "subtotal_minor": 120000,
    "added_items": [],
    "removed_items": []
  }
}
```

**Rationale**: Snapshot at write time (not computed on read) means the diff is immutable and survives subsequent modifications. The frontend gets a complete before/after without additional queries.

**Alternatives considered**:
- Compute diff on read from `booking_modification_items` — rejected because it requires joining multiple tables and the schema already provides `diff_snapshot` as a denormalized field.
- Store only the delta — rejected because the `before` state is needed for visual highlighting.

---

## Decision 2: Booking Lifecycle State Transitions

**Question**: What is the exact state machine for `lifecycle_status` through the negotiation loop?

**Decision**:

```
draft
  → submitted (by customer, via SubmitBookingAction)
  → vendor_review (immediately after submitted; set within same transaction)
  ↔ customer_review (toggled by vendor modification proposal / customer decision)
  → confirmed (when ALL booking_vendors have sub_status = accepted)
  → cancelled (when ALL booking_vendors have sub_status = rejected, or customer explicitly cancels)
```

`VendorSubStatus` transitions:
```
pending → accepted    (VendorAcceptBookingAction)
pending → modified    (VendorModifyBookingAction)
pending → rejected    (VendorRejectBookingAction)
modified → pending    (CustomerConfirmModifiedBookingAction with decision=rejected)
```

The booking moves to `customer_review` when any vendor transitions to `modified`.
The booking moves back to `vendor_review` when the customer rejects a modification (resetting that vendor to `pending`).
The booking moves to `confirmed` only when every `booking_vendor.sub_status = accepted`.

**Rationale**: Matches schema ENUM values in `booking.lifecycle_status` and `booking_vendors.sub_status` exactly. All transitions stored in `booking_state_transitions`.

---

## Decision 3: Idempotency Implementation

**Question**: How should idempotency be enforced on `SubmitBookingAction` and `CustomerConfirmModifiedBookingAction`?

**Decision**: Use the existing `idempotency_keys` table (schema already defined in `docs/specs/11_DB_Schema.md`). The `Idempotency-Key` header is required on the submit and confirm-modification endpoints. Each action checks at the top of `execute()` for an existing key before performing work. The stored result is the serialized JSON response with 24h TTL.

**Rationale**: Consistent with existing idempotency pattern in the codebase (already referenced in CLAUDE.md and payment endpoints). Avoids custom locking logic.

---

## Decision 4: Multi-Vendor State Aggregation

**Question**: When does the booking advance to `confirmed`? Who is responsible for checking all-vendors-accepted?

**Decision**: `VendorAcceptBookingAction` checks — after setting the current vendor's `sub_status = accepted` — whether all `booking_vendor` rows for this booking have `sub_status = accepted`. If so, it updates `bookings.lifecycle_status = confirmed` and `confirmed_at = now()` within the same transaction.

Similarly, when `VendorRejectBookingAction` runs, it checks if ALL vendors have `sub_status = rejected`. If so, it moves the booking to `cancelled`.

**Rationale**: Keeps the aggregation logic in the Action layer (application), not in an event listener, making it synchronous and testable without event firing.

---

## Decision 5: Modification Application Strategy

**Question**: When the customer accepts a modification, how are the changes applied to `booking_items`?

**Decision**: `CustomerConfirmModifiedBookingAction` iterates `booking_modification_items` for the accepted modification and applies each change:
- `change_kind = update` → `UPDATE booking_items SET ... WHERE id = target_booking_item_id`
- `change_kind = add` → `INSERT INTO booking_items ...` from the `payload` JSON
- `change_kind = remove` → `DELETE FROM booking_items WHERE id = target_booking_item_id`

After applying changes, `RecalculateBookingTotalsListener` is fired (via `CustomerModificationDecided` event) to recalculate `booking_vendor.subtotal_minor` and `bookings.total_minor`.

All within a single `DB::transaction`.

**Rationale**: Explicit and auditable. Each change is applied individually, matching the stored `change_kind` contract exactly.

---

## Decision 6: Booking Snapshot Trigger Points

**Question**: When should new `booking_snapshots` rows be created during negotiation?

**Decision**: A new snapshot is created after each of these events:
1. `BookingSubmittedToVendor` (one snapshot per submit, not per vendor)
2. `VendorModificationProposed`
3. `VendorAccepted` (when booking reaches `confirmed`)
4. `VendorRejected`
5. `CustomerModificationDecided`

Handled by `WriteNegotiationSnapshotListener` which listens to all five events. Each snapshot captures the full resolved booking state (all vendors, all items, current totals).

**Rationale**: Each negotiation event is a meaningful state change visible to the customer. The UI reads the latest snapshot for a given booking to render current state.

---

## Decision 7: Filament BookingsMonitor Scope

**Question**: What does the Phase 1 admin monitor show and can admin take actions?

**Decision**: `BookingsMonitorResource` is a **read-only** Filament Resource (no CreateAction, no EditAction, no DeleteAction). It lists bookings with `lifecycle_status IN ('vendor_review', 'customer_review')` with columns: reference_no, customer name, product_type breakdown (via booking_items count per type), total_minor, submitted_at, and the nearest vendor `response_deadline`. Includes a SelectFilter on `product_type` (Rental/Sale/Digital). Admin cannot intervene programmatically in Phase 1.

**Rationale**: FR-16 requires monitoring. FR-17 explicitly prohibits admin-forced vendor replacement. Phase 7.0 will add intervention tools.

---

## Decision 8: Inventory Reservation Upgrade on Confirmation

**Question**: When a Rental item's booking is confirmed, how does the `ServiceInventoryReservation` transition from `held` to `confirmed`?

**Decision**: `ConfirmInventoryReservationsListener` listens to a new `BookingConfirmed` event fired by `VendorAcceptBookingAction` when the last vendor accepts. The listener uses `DB::table('service_inventory_reservations')` (raw query, no cross-module Eloquent) to upgrade all `held` reservations associated with `booking_items` in this booking to `confirmed`.

**Rationale**: Consistent with existing pattern in `RemoveItemFromBookingAction` which uses raw `DB::table()` instead of importing `ServiceInventoryReservation` model.
