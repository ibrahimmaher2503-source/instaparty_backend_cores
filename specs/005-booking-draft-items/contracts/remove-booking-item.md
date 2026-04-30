# Contract: Remove Item from Booking

**Endpoint**: `DELETE /api/v1/customer/bookings/{bookingPublicId}/items/{itemPublicId}`
**Auth**: `auth:sanctum` — customer role, must own the booking
**Action**: `RemoveItemFromBookingAction`

---

## Request

**Path params**:
- `bookingPublicId` — ULID of the draft booking
- `itemPublicId` — ULID of the booking item to remove

No request body.

---

## Response — 200 OK

```json
{
  "data": {
    "booking_public_id": "01HXXX...",
    "removed_item_public_id": "01HITEM...",
    "booking_total_minor": 0,
    "booking_currency": "EGP",
    "vendors": []
  },
  "meta": {},
  "errors": null
}
```

**Side effects**:
- `service_inventory_reservations` row updated to `status = released`, `released_at = now()`, `release_reason = customer_cancelled`
- `booking_items` row deleted (hard delete)
- If no items remain for the vendor: `booking_vendors` row deleted
- `booking.subtotal_minor` and `booking.total_minor` recalculated

---

## Error Responses

| Status | Condition |
|---|---|
| 401 | Missing or invalid auth |
| 403 | Booking belongs to another customer |
| 404 | Booking or item not found |
| 409 | Booking is not in `draft` status |
