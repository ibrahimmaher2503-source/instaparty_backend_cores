# API Contracts: Admin Changes-Requested Workflow

**Branch**: `020-admin-changes-request` | **Date**: 2026-05-04

All endpoints use:
- Standard `ApiResponse` envelope: `{ data, meta, errors }`
- Bearer token auth via Sanctum
- `Accept-Language: en|ar` for locale
- `Idempotency-Key: <uuid>` required on all state-mutating endpoints

---

## Admin Endpoints

### POST /api/v1/admin/vendor-profiles/{publicId}/change-requests

**Module**: Identity → `admin.php`
**Controller**: `VendorChangeRequestController@store`
**Action**: `RequestVendorChangesAction`
**Guard**: `auth:sanctum` + `role:admin` + `can:update_vendor_profile`
**Required header**: `Idempotency-Key`

**Request body**:
```json
{
  "items": [
    {
      "field_path": "documents.cr_document",
      "requested_change_en": "CR document is blurry — please re-upload a clear scan",
      "requested_change_ar": "مستند السجل التجاري غير واضح — يرجى رفع نسخة واضحة"
    },
    {
      "field_path": "bank_details.iban",
      "requested_change_en": "IBAN does not match business name on CR",
      "requested_change_ar": "رقم IBAN لا يتطابق مع اسم النشاط في السجل التجاري"
    }
  ]
}
```

**Validation rules**:
- `items`: required, array, min:1
- `items.*.field_path`: required, string, max:255
- `items.*.requested_change_en`: required, string, min:5
- `items.*.requested_change_ar`: required, string, min:5

**Response 201**:
```json
{
  "data": {
    "public_id": "01HWZB3K9XFVTM6P4QD7RNSE2A",
    "subject_type": "vendor_profile",
    "subject_public_id": "01HWZA1K9XFVTM6P4QD7RNSE2B",
    "status": "open",
    "cycle_number": 1,
    "items": [
      {
        "public_id": "01HWZB3K9XFVTM6P4QD7RNSE2C",
        "field_path": "documents.cr_document",
        "requested_change_en": "CR document is blurry — please re-upload a clear scan",
        "requested_change_ar": "مستند السجل التجاري غير واضح — يرجى رفع نسخة واضحة",
        "item_status": "pending"
      }
    ],
    "created_at": "2026-05-04T10:00:00Z"
  },
  "meta": {},
  "errors": []
}
```

**Error responses**:
- `404` — vendor profile not found
- `409` — vendor profile already has an open or resubmitted change request
- `422` — validation failure (missing EN or AR text)
- `422` — "Maximum change cycles reached" when cycle_number would exceed 3

---

### POST /api/v1/admin/services/{publicId}/change-requests

**Module**: Catalog → `admin.php`
**Controller**: `ServiceChangeRequestController@store`
**Action**: `RequestServiceChangesAction`
**Guard**: `auth:sanctum` + `role:admin` + `can:moderate_{product_type}_service`
**Required header**: `Idempotency-Key`

**Request body**: Same shape as vendor endpoint above (items array with field_path + EN + AR).

**Response 201**: Same envelope; `subject_type` = `"service"`.

**Error responses**:
- `404` — service not found
- `409` — service already has an open or resubmitted change request
- `422` — validation failure
- `422` — "Maximum change cycles reached"

---

### POST /api/v1/admin/change-requests/{publicId}/escalate

**Module**: Shared → loaded via `Shared\Routes\admin.php`
**Controller**: `ChangeRequestEscalationController@store`
**Action**: `EscalateChangeRequestToRejectionAction`
**Guard**: `auth:sanctum` + `role:admin`
**Required header**: `Idempotency-Key`

**Request body**:
```json
{
  "escalation_notes": "Vendor did not resolve IBAN mismatch after 3 cycles"
}
```

**Validation rules**:
- `escalation_notes`: nullable, string, max:1000

**Guard**: Change request must be `status=resubmitted` AND `cycle_number === MAX_CYCLES`. Otherwise returns `403 Forbidden`.

**Response 200**:
```json
{
  "data": {
    "public_id": "01HWZB3K9XFVTM6P4QD7RNSE2A",
    "status": "escalated_to_rejection",
    "resolved_at": "2026-05-04T12:00:00Z"
  },
  "meta": {},
  "errors": []
}
```

---

## Vendor Endpoints

### GET /api/v1/vendor/change-requests

**Module**: Shared → `vendor.php`
**Controller**: `VendorChangeRequestListController@index`
**Guard**: `auth:sanctum` + `role:vendor`

**Query params**:
- `status`: optional, one of `open|resubmitted|resolved|escalated_to_rejection` (default: `open`)
- `per_page`: optional, int, default 15

**Response 200**:
```json
{
  "data": [
    {
      "public_id": "01HWZB3K9XFVTM6P4QD7RNSE2A",
      "subject_type": "vendor_profile",
      "subject_public_id": "01HWZA1K9XFVTM6P4QD7RNSE2B",
      "status": "open",
      "cycle_number": 1,
      "items": [
        {
          "public_id": "01HWZB3K9XFVTM6P4QD7RNSE2C",
          "field_path": "documents.cr_document",
          "requested_change_en": "CR document is blurry — please re-upload a clear scan",
          "requested_change_ar": "مستند السجل التجاري غير واضح — يرجى رفع نسخة واضحة",
          "item_status": "pending"
        }
      ],
      "created_at": "2026-05-04T10:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 1
  },
  "errors": []
}
```

---

### POST /api/v1/vendor/vendor-profiles/{publicId}/resubmit

**Module**: Identity → `vendor.php`
**Controller**: `VendorProfileResubmitController@store`
**Action**: `VendorResubmitAfterChangesAction`
**Guard**: `auth:sanctum` + `role:vendor` + ownership check (vendor owns this profile)
**Required header**: `Idempotency-Key`

**Request body**:
```json
{
  "addressed_item_ids": ["01HWZB3K9XFVTM6P4QD7RNSE2C"],
  "waived_item_ids": [],
  "resubmit_notes": "Uploaded fresh CR scan and corrected IBAN"
}
```

**Validation rules**:
- `addressed_item_ids`: required, array (if empty, vendor claims all change request items have been addressed)
- `addressed_item_ids.*`: string, must be valid `change_request_items.public_id` belonging to this request
- `waived_item_ids`: optional, array
- `resubmit_notes`: optional, string, max:1000

**Guard**: Vendor profile `approval_status` must be `changes_requested`. Active change request must have `status=open`. Returns `409` otherwise.

**Response 200**:
```json
{
  "data": {
    "vendor_profile_public_id": "01HWZA1K9XFVTM6P4QD7RNSE2B",
    "approval_status": "pending",
    "change_request": {
      "public_id": "01HWZB3K9XFVTM6P4QD7RNSE2A",
      "status": "resubmitted",
      "cycle_number": 1
    }
  },
  "meta": {},
  "errors": []
}
```

---

### POST /api/v1/vendor/services/{publicId}/resubmit

**Module**: Catalog → `vendor.php`
**Controller**: `ServiceResubmitController@store`
**Action**: `VendorResubmitServiceAfterChangesAction`
**Guard**: `auth:sanctum` + `role:vendor` + ownership check (service belongs to vendor)
**Required header**: `Idempotency-Key`

**Request body**: Same shape as vendor profile resubmit above.

**Guard**: Service `status` must be `changes_requested`. Active change request must have `status=open`. Returns `409` otherwise.

**Response 200**: Same envelope with `service_public_id` + `status: "pending_review"`.

---

## Filament Admin Actions (not REST — Filament action closures)

### "Request Changes" button on VendorProfileResource

- Visible when: `$record->approval_status === ApprovalStatus::Pending || $record->approval_status === ApprovalStatus::ChangesRequested`
- Hidden when: already has `status=open` change request (cycle must be resolved first)
- Form: `Repeater` with `field_path` + `requested_change_en` + `requested_change_ar`
- Delegates to: `RequestVendorChangesAction::execute($vendorProfile, $items, auth()->user())`

### "Request Changes" button on RentalServiceResource, SaleServiceResource, DigitalServiceResource

- Visible when: `$record->status === ServiceStatus::PendingReview`
- Same form and delegation pattern

### "Force Reject (cycle limit)" button on VendorProfileResource + Service Resources

- Visible when: active change request has `status=resubmitted` AND `cycle_number === 3`
- Delegates to: `EscalateChangeRequestToRejectionAction::execute($changeRequest, auth()->user(), $notes)`
- Requires confirmation modal
