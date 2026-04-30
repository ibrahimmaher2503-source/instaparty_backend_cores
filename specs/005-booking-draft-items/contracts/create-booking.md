# Contract: Create Draft Booking

**Endpoint**: `POST /api/v1/customer/bookings`
**Auth**: `auth:sanctum` — customer role required
**Action**: `CreateBookingDraftAction`

---

## Request

**Headers**:
```
Authorization: Bearer {token}      (mobile)  OR  Cookie (SPA)
Content-Type: application/json
Accept-Language: ar                           (or en)
```

**Body**:
```json
{
  "occasion_id": "01HXYZ...",
  "event_starts_at": "2026-07-15T18:00:00Z",
  "event_ends_at": "2026-07-15T23:00:00Z",
  "guest_count": 50,
  "theme": { "en": "Frozen", "ar": "فروزن" },
  "celebrant_name": "Layla",
  "celebrant_dob": "2020-07-15",
  "celebrant_gender": "female",
  "address": {
    "city_id": "01HABC...",
    "address_line": "12 Nile St",
    "building": "Tower B",
    "floor": "3",
    "apartment": "301",
    "landmark": "Next to Mall",
    "recipient_name": "Aya Maher",
    "recipient_phone_e164": "+201012345678"
  }
}
```

**Validation rules**:

| Field | Rule |
|---|---|
| `occasion_id` | required, exists:occasions,public_id |
| `event_starts_at` | required, date, after:now |
| `event_ends_at` | required, date, after:event_starts_at |
| `guest_count` | nullable, integer, min:1 |
| `theme` | nullable, array with keys `en`, `ar` |
| `address.city_id` | required, exists:cities,public_id |
| `address.address_line` | required, string, max:255 |
| `address.recipient_name` | required, string, max:120 |
| `address.recipient_phone_e164` | required, regex E.164 |

---

## Response — 201 Created

```json
{
  "data": {
    "public_id": "01HXXX...",
    "reference_no": "IP-2026-000001",
    "lifecycle_status": "draft",
    "payment_status": "unpaid",
    "fulfillment_status": "not_started",
    "event_starts_at": "2026-07-15T21:00:00+03:00",
    "event_ends_at": "2026-07-16T02:00:00+03:00",
    "subtotal_minor": 0,
    "delivery_total_minor": 0,
    "total_minor": 0,
    "currency": "EGP",
    "vendors": [],
    "address": {
      "city_id": "01HABC...",
      "address_line": "12 Nile St",
      "recipient_name": "Aya Maher",
      "recipient_phone_e164": "+201012345678"
    }
  },
  "meta": {},
  "errors": null
}
```

**Notes**:
- Timestamps in response are converted to the customer's timezone (from `users.timezone`) at the `BookingResource` layer.
- `total_minor` is 0 at creation; computed after items are added.

---

## Error Responses

| Status | Condition |
|---|---|
| 401 | Missing or invalid auth |
| 422 | Validation failure (field errors in `errors.fields`) |
| 409 | Customer already has a draft booking (deferred rule — Phase 3.1 allows multiple drafts) |
