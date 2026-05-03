# Contract: Vendor Booking Actions

**Actor**: Authenticated Vendor
**Auth**: Sanctum cookie / token (role: `vendor`)

---

## GET /api/v1/vendor/booking-vendors

Lists all `booking_vendor` rows assigned to the authenticated vendor with `sub_status = pending`.

### Success Response — 200 OK

```json
{
  "data": [
    {
      "public_id": "01J...",
      "booking_public_id": "01J...",
      "booking_reference_no": "IP-2026-000123",
      "sub_status": "pending",
      "response_deadline": "2026-06-01T10:00:00+03:00",
      "subtotal_minor": 100000,
      "currency": "EGP",
      "items": [
        {
          "public_id": "01J...",
          "product_type": "rental",
          "name": "Inflatable Castle",
          "unit_price_minor": 50000,
          "quantity": 2,
          "effective_starts_at": "2026-06-15T09:00:00+03:00",
          "effective_ends_at": "2026-06-15T18:00:00+03:00"
        }
      ]
    }
  ],
  "meta": {},
  "errors": null
}
```

---

## POST /api/v1/vendor/booking-vendors/{booking_vendor_public_id}/accept

Vendor accepts their portion of the booking as-is.

### Request Body

```json
{}
```

### Success Response — 200 OK

```json
{
  "data": {
    "public_id": "01J...",
    "sub_status": "accepted",
    "responded_at": "2026-06-01T08:00:00+03:00"
  },
  "meta": {},
  "errors": null
}
```

### Error Responses

| Status | Condition |
|---|---|
| 401 | Unauthenticated |
| 403 | booking_vendor does not belong to this vendor |
| 409 | booking_vendor is not in `pending` status |

---

## POST /api/v1/vendor/booking-vendors/{booking_vendor_public_id}/modify

Vendor proposes modifications to their assigned items.

### Request Body

```json
{
  "proposal_kind": "change_price",
  "vendor_explanation": {
    "en": "Raw material costs increased",
    "ar": "ارتفعت تكاليف المواد الخام"
  },
  "changes": [
    {
      "change_kind": "update",
      "target_item_public_id": "01J...",
      "payload": {
        "unit_price_minor": 60000,
        "unit_price_currency": "EGP"
      }
    }
  ]
}
```

For adding a new item (`change_kind: add`), `target_item_public_id` is omitted and `payload` contains the full new item fields (matching `booking_items` columns).

For removing an item (`change_kind: remove`), only `target_item_public_id` is needed; `payload` may be empty.

### Success Response — 201 Created

```json
{
  "data": {
    "public_id": "01J...",
    "proposal_kind": "change_price",
    "status": "pending",
    "diff_snapshot": {
      "before": { "items": [{ "public_id": "01J...", "unit_price_minor": 50000 }], "subtotal_minor": 50000 },
      "after":  { "items": [{ "public_id": "01J...", "unit_price_minor": 60000 }], "subtotal_minor": 60000 }
    },
    "expires_at": null
  },
  "meta": {},
  "errors": null
}
```

### Error Responses

| Status | Condition |
|---|---|
| 401 | Unauthenticated |
| 403 | booking_vendor does not belong to this vendor |
| 409 | booking_vendor is not in `pending` status OR a pending modification already exists |
| 422 | Validation failure (missing fields, invalid `proposal_kind`, etc.) |

---

## POST /api/v1/vendor/booking-vendors/{booking_vendor_public_id}/reject

Vendor rejects their assigned portion.

### Request Body

```json
{
  "rejection_reason": {
    "en": "We are fully booked on that date",
    "ar": "لدينا حجوزات كاملة في ذلك اليوم"
  }
}
```

`rejection_reason` is optional.

### Success Response — 200 OK

```json
{
  "data": {
    "public_id": "01J...",
    "sub_status": "rejected",
    "responded_at": "2026-06-01T08:15:00+03:00"
  },
  "meta": {},
  "errors": null
}
```

### Error Responses

| Status | Condition |
|---|---|
| 401 | Unauthenticated |
| 403 | booking_vendor does not belong to this vendor |
| 409 | booking_vendor is not in `pending` status |
