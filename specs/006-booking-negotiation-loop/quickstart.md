# Quickstart: Booking Negotiation Loop

Integration test scenarios for the full negotiation lifecycle. Use these as a reference for Pest feature tests.

---

## Scenario 1: Happy Path — Single Vendor Accepts (all 3 product types)

```
1. Create customer + vendor (approved for Rental, Sale, Digital)
2. Create draft booking with items from this vendor (1 Rental, 1 Sale, 1 Digital)
3. POST /api/v1/bookings/{id}/submit  [Idempotency-Key: key-001]
   → booking.lifecycle_status = 'vendor_review'
   → booking_vendor.sub_status = 'pending'
   → booking_vendor.response_deadline = now() + 24h
   → booking_state_transitions has row: to_state='vendor_review'
   → idempotency_keys has row for key-001

4. POST /api/v1/vendor/booking-vendors/{bv_id}/accept
   → booking_vendor.sub_status = 'accepted'
   → booking.lifecycle_status = 'confirmed'
   → booking.confirmed_at IS NOT NULL
   → booking_state_transitions has row: to_state='confirmed'
   → service_inventory_reservations.status = 'confirmed' (for Rental item)

5. Repeat step 3 with same Idempotency-Key → 200 same response, no new state_transitions
```

---

## Scenario 2: Vendor Modifies → Customer Accepts

```
1. Set up draft booking (same as Scenario 1 step 1-2, Rental item only)
2. Submit booking → vendor_review
3. POST /api/v1/vendor/booking-vendors/{bv_id}/modify
   Body: { proposal_kind: "change_price", changes: [{change_kind: "update", target_item_public_id: "...", payload: {unit_price_minor: 60000}}] }
   → booking_modifications row created (status=pending)
   → booking_modification_items row created (change_kind=update)
   → booking.lifecycle_status = 'customer_review'
   → booking_vendor.sub_status = 'modified'
   → diff_snapshot.before.items[0].unit_price_minor = 50000
   → diff_snapshot.after.items[0].unit_price_minor = 60000

4. GET /api/v1/bookings/{id}/modifications
   → returns modification with diff_snapshot

5. POST /api/v1/bookings/{id}/modifications/{mod_id}/decide  [Idempotency-Key: key-002]
   Body: { decision: "accepted" }
   → booking_modifications.status = 'customer_accepted'
   → booking_items row updated: unit_price_minor = 60000
   → booking_vendor.subtotal_minor recalculated = 60000
   → booking.total_minor recalculated = 60000
   → booking.lifecycle_status = 'confirmed'
   → booking_state_transitions has row: to_state='confirmed'
```

---

## Scenario 3: Vendor Modifies → Customer Rejects → Vendor Accepts (Loop)

```
1. Set up + submit (Sale item)
2. Vendor modifies (add_surcharge: +5000)
   → customer_review, booking_vendor.sub_status=modified
3. Customer rejects modification
   → booking_modifications.status = 'customer_rejected'
   → booking.lifecycle_status = 'vendor_review'
   → booking_vendor.sub_status = 'pending'     ← reset to pending for new attempt
   → booking_items unchanged (prices not applied)
4. Vendor accepts (second attempt)
   → booking.lifecycle_status = 'confirmed'
```

---

## Scenario 4: Multi-Vendor Partial Acceptance

```
1. Draft booking with items from Vendor A (Sale) and Vendor B (Digital)
2. Submit → both booking_vendors: sub_status=pending
3. Vendor A accepts
   → Vendor A: sub_status=accepted
   → booking.lifecycle_status still = 'vendor_review' (Vendor B still pending)
4. Vendor B accepts
   → Vendor B: sub_status=accepted
   → booking.lifecycle_status = 'confirmed' (all vendors accepted)
```

---

## Scenario 5: All Vendors Reject → Booking Cancelled

```
1. Draft booking with items from Vendor A (Rental) and Vendor B (Sale)
2. Submit → both pending
3. Vendor A rejects
   → Vendor A: sub_status=rejected
   → booking.lifecycle_status = 'customer_review' (Vendor B still pending)
4. Vendor B rejects
   → Vendor B: sub_status=rejected
   → booking.lifecycle_status = 'cancelled'
   → service_inventory_reservations.status = 'released' (for Rental item)
   → No auto-replacement vendor assigned
```

---

## Scenario 6: Vendor Adds New Item via Modification

```
1. Draft booking with 1 Rental item (booking_items count=1)
2. Submit → vendor_review
3. Vendor proposes add_item modification
   Body: { proposal_kind: "add_item", changes: [{change_kind: "add", payload: { service_id: 99, product_type: "rental", unit_price_minor: 30000, ... }}] }
   → booking_modification_items: change_kind=add, target_booking_item_id=NULL
   → customer_review
4. Customer accepts
   → new booking_items row created from payload
   → booking_items count=2
   → totals recalculated
```

---

## Test Helpers

For all Pest feature tests, ensure:

```php
beforeEach(function(): void {
    $this->seed(IdentityRolesSeeder::class);
});
```

Use `User::factory()->asCustomer()->create()` and `User::factory()->asVendor()->create()` for actors.

Each test group should use `->group('booking', 'negotiation')`.

Per-type tests:
```php
it('confirms rental booking after vendor accept', function() { ... })->group('rental', 'negotiation');
it('confirms sale booking after vendor accept', function() { ... })->group('sale', 'negotiation');
it('confirms digital booking after vendor accept', function() { ... })->group('digital', 'negotiation');
```
