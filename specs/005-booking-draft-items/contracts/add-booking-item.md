# Contract: Add Item to Booking

**Endpoint**: `POST /api/v1/customer/bookings/{bookingPublicId}/items`
**Auth**: `auth:sanctum` — customer role, must own the booking
**Action**: `AddItemToBookingAction`

---

## Request

**Path param**: `bookingPublicId` — ULID of the draft booking

**Body**:
```json
{
  "service_id": "01HSERVICE...",
  "quantity": 1,
  "effective_starts_at": "2026-07-15T18:00:00Z",
  "effective_ends_at": "2026-07-15T23:00:00Z",
  "customization_data": {}
}
```

**Validation rules**:

| Field | Rule |
|---|---|
| `service_id` | required, exists:services,public_id, status=published |
| `quantity` | required, integer, min:1 |
| `effective_starts_at` | nullable, date (defaults to `booking.event_starts_at`) |
| `effective_ends_at` | nullable, date, after:effective_starts_at |
| `customization_data` | nullable, array |

---

## Response — 201 Created

```json
{
  "data": {
    "public_id": "01HITEM...",
    "service_id": "01HSERVICE...",
    "product_type": "rental",
    "name": "Frozen Bouncy Castle",
    "unit_price_minor": 150000,
    "quantity": 1,
    "line_total_minor": 150000,
    "currency": "EGP",
    "item_status": "pending_delivery",
    "vendor": {
      "public_id": "01HVENDOR...",
      "business_name": "Magic Inflatables",
      "subtotal_minor": 150000,
      "delivery_fee_minor": 5000
    },
    "reservation_expires_at": "2026-04-30T14:15:00+03:00"
  },
  "meta": {
    "booking_total_minor": 155000,
    "booking_currency": "EGP"
  },
  "errors": null
}
```

**Side effects**:
- `service_inventory_reservations` row created with `status = held`, `expires_at = now + 15 min` (rental/sale only)
- `booking_vendors` row created or subtotal updated
- `booking.subtotal_minor` and `booking.total_minor` recalculated

---

## Error Responses

| Status | Condition |
|---|---|
| 401 | Missing or invalid auth |
| 403 | Booking belongs to another customer |
| 404 | Booking not found |
| 409 | Booking is not in `draft` status |
| 409 | Service is out of stock / unavailable for the requested dates |
| 422 | Validation failure |
