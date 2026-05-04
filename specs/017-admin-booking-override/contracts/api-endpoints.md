# API Contracts: Admin Booking Override

**Feature**: Phase 6.5 — Admin Booking Override
**Date**: 2026-05-03

All responses wrapped in standard `ApiResponse` envelope: `{ data, meta, errors }`.

---

## Admin Endpoints

### POST /api/v1/admin/bookings/{bookingPublicId}/force-cancel

**Auth**: `sanctum-token` | **Roles**: `super_admin`, `booking_manager`
**Idempotency-Key**: Required (24h TTL)

**Request Body** (ForceCancelBookingRequest):
```json
{
  "reason": "Vendor has gone inactive — customer needs resolution before event date"
}
```

**Response 200**:
```json
{
  "data": {
    "intervention_public_id": "01HZ9QWERTY1234567890ABCD",
    "booking_public_id": "01HZ9BOOKING123456789012",
    "intervention_type": "force_cancel",
    "before_lifecycle_status": "vendor_review",
    "after_lifecycle_status": "cancelled",
    "refund_initiated": true,
    "created_at": "2026-05-03T14:32:00Z"
  },
  "meta": {},
  "errors": []
}
```

**Error 422**: Reason field empty
**Error 409**: Booking already in `completed` state (cannot force-cancel completed bookings)
**Error 403**: Caller lacks `force_cancel_booking` permission

---

### POST /api/v1/admin/bookings/{bookingPublicId}/timeout-vendor

**Auth**: `sanctum-token` | **Roles**: `super_admin`, `booking_manager`
**Idempotency-Key**: Required

**Request Body** (TimeoutVendorResponseRequest):
```json
{
  "reason": "Vendor has not responded in 36 hours — manual timeout applied"
}
```

**Response 200**:
```json
{
  "data": {
    "intervention_public_id": "01HZ9QWERTY2234567890ABCD",
    "booking_public_id": "01HZ9BOOKING123456789012",
    "intervention_type": "vendor_timeout",
    "before_lifecycle_status": "vendor_review",
    "after_lifecycle_status": "cancelled",
    "created_at": "2026-05-03T14:45:00Z"
  },
  "meta": {},
  "errors": []
}
```

**Error 422**: Booking not in `vendor_review` state
**Error 422**: Reason field empty
**Error 403**: Caller lacks `timeout_vendor_response` permission

---

### POST /api/v1/admin/bookings/{bookingPublicId}/propose-vendor

**Auth**: `sanctum-token` | **Roles**: `super_admin`, `booking_manager`
**Idempotency-Key**: Required

**Request Body** (ProposeAlternativeVendorRequest):
```json
{
  "proposed_vendor_public_id": "01HZ9VENDOR12345678901234",
  "reason": "Original vendor cancelled — this vendor confirmed availability for the event date",
  "consent_ttl_hours": 48
}
```

**Response 201**:
```json
{
  "data": {
    "intervention_public_id": "01HZ9QWERTY3234567890ABCD",
    "booking_public_id": "01HZ9BOOKING123456789012",
    "intervention_type": "vendor_proposal",
    "proposed_vendor": {
      "public_id": "01HZ9VENDOR12345678901234",
      "display_name": { "en": "Party Dreams Co.", "ar": "شركة أحلام الحفلات" }
    },
    "customer_consent_status": "pending",
    "consent_expires_at": "2026-05-05T14:45:00Z",
    "created_at": "2026-05-03T14:45:00Z"
  },
  "meta": {},
  "errors": []
}
```

**Error 422**: A pending proposal already exists for this booking
**Error 422**: Proposed vendor not approved for the booking's product type
**Error 403**: Caller lacks `propose_alternative_vendor` permission

---

### POST /api/v1/admin/bookings/{bookingPublicId}/notes

**Auth**: `sanctum-token` | **Roles**: `super_admin`, `booking_manager`

**Request Body** (AddAdminNoteRequest):
```json
{
  "reason": "Called vendor by phone — confirmed they are aware of booking, response expected within 2 hours"
}
```

**Response 201**:
```json
{
  "data": {
    "intervention_public_id": "01HZ9QWERTY4234567890ABCD",
    "booking_public_id": "01HZ9BOOKING123456789012",
    "intervention_type": "admin_note",
    "note": "Called vendor by phone — confirmed they are aware of booking, response expected within 2 hours",
    "created_at": "2026-05-03T14:50:00Z"
  },
  "meta": {},
  "errors": []
}
```

**Error 422**: Note body empty
**Error 403**: Caller lacks `add_booking_note` permission

---

### GET /api/v1/admin/bookings/{bookingPublicId}/interventions

**Auth**: `sanctum-token` | **Roles**: `super_admin`, `booking_manager`

**Response 200** (list, cursor-paginated):
```json
{
  "data": [
    {
      "public_id": "01HZ9QWERTY1234567890ABCD",
      "intervention_type": "force_cancel",
      "reason": "Vendor gone inactive",
      "before_state": { "lifecycle_status": "vendor_review", "payment_status": "paid", "fulfillment_status": "not_started" },
      "after_state": { "lifecycle_status": "cancelled", "payment_status": "refund_pending", "fulfillment_status": "not_started" },
      "customer_consent_status": null,
      "admin": { "name": "Ibrahim Admin" },
      "created_at": "2026-05-03T14:32:00Z"
    }
  ],
  "meta": { "next_cursor": null },
  "errors": []
}
```

---

## Customer Endpoints

### PATCH /api/v1/customer/bookings/{bookingPublicId}/vendor-proposals/{interventionPublicId}/respond

**Auth**: `sanctum-token` | **Roles**: `customer`

**Request Body** (RespondToVendorProposalRequest):
```json
{
  "decision": "accepted"
}
```
*`decision` must be `"accepted"` or `"rejected"`*

**Response 200**:
```json
{
  "data": {
    "intervention_public_id": "01HZ9QWERTY3234567890ABCD",
    "customer_consent_status": "accepted",
    "booking_public_id": "01HZ9BOOKING123456789012",
    "updated_at": "2026-05-03T16:00:00Z"
  },
  "meta": {},
  "errors": []
}
```

**Error 404**: Intervention not found or does not belong to this customer's booking
**Error 422**: Proposal is not in `pending` state (already decided or expired)
**Error 422**: Decision value not `accepted` or `rejected`
