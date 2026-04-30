# Contract: Customer Submit Booking

**Actor**: Authenticated Customer
**Auth**: Sanctum cookie / token (role: `customer`)

---

## POST /api/v1/bookings/{booking_public_id}/submit

### Headers

```
Authorization: Bearer {token}   (or Sanctum cookie)
Idempotency-Key: {uuid}         (required)
Accept: application/json
```

### Request Body

```json
{}
```

No body required — all data comes from the existing draft booking.

### Success Response — 200 OK

```json
{
  "data": {
    "public_id": "01J...",
    "reference_no": "IP-2026-000123",
    "lifecycle_status": "vendor_review",
    "vendors": [
      {
        "public_id": "01J...",
        "vendor_profile_id": 12,
        "sub_status": "pending",
        "response_deadline": "2026-06-01T10:00:00+03:00"
      }
    ]
  },
  "meta": {},
  "errors": null
}
```

### Error Responses

| Status | Condition |
|---|---|
| 401 | Unauthenticated |
| 403 | Booking does not belong to this customer |
| 404 | Booking not found |
| 409 | Booking is not in `draft` status |
| 422 | Booking has no items (empty draft cannot be submitted) |

### Idempotency

Duplicate requests with the same `Idempotency-Key` within 24h return the stored response (status 200) without re-submitting.

---

## GET /api/v1/bookings/{booking_public_id}/modifications

Lists all pending or decided modifications on a booking the customer owns.

### Success Response — 200 OK

```json
{
  "data": [
    {
      "public_id": "01J...",
      "booking_vendor_id": "01J...",
      "proposal_kind": "change_price",
      "status": "pending",
      "vendor_explanation": { "en": "Material cost increased", "ar": "ارتفعت تكاليف المواد" },
      "diff_snapshot": {
        "before": { "items": [{ "public_id": "01J...", "unit_price_minor": 50000 }], "subtotal_minor": 50000 },
        "after":  { "items": [{ "public_id": "01J...", "unit_price_minor": 60000 }], "subtotal_minor": 60000 }
      },
      "created_at": "2026-06-01T08:30:00+03:00"
    }
  ],
  "meta": {},
  "errors": null
}
```

---

## POST /api/v1/bookings/{booking_public_id}/modifications/{modification_public_id}/decide

Customer accepts or rejects a pending modification.

### Headers

```
Authorization: Bearer {token}
Idempotency-Key: {uuid}   (required)
```

### Request Body

```json
{
  "decision": "accepted"   // or "rejected"
}
```

### Success Response — 200 OK

```json
{
  "data": {
    "public_id": "01J...",
    "lifecycle_status": "confirmed",   // or "vendor_review" if decision=rejected
    "total_minor": 60000,
    "currency": "EGP"
  },
  "meta": {},
  "errors": null
}
```

### Error Responses

| Status | Condition |
|---|---|
| 401 | Unauthenticated |
| 403 | Booking does not belong to this customer |
| 404 | Modification not found |
| 409 | Modification is not in `pending` status |
| 422 | `decision` not in `[accepted, rejected]` |
