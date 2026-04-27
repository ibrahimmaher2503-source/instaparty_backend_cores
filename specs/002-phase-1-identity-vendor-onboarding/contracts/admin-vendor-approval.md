# Contract: Admin Vendor Approval

**Module**: Identity
**Audience**: Filament admin panel (internal Filament Actions — not REST API)
**Auth**: Filament Shield permissions

---

## Filament Action: ApproveVendorForType

Invoked from `VendorApprovalQueueResource` table row action.

**Permission required**: `approve_vendor_for_{type}` (e.g., `approve_vendor_for_rental`)

**Form fields**:
```
product_type: rental | sale | digital  (Select, required)
```

**Action calls**: `ApproveVendorForTypeAction::execute(VendorProfile $vendor, ProductType $type)`

**Side effects**:
- Creates row in `vendor_approved_product_types` (revoked_at = null)
- Grants Spatie permissions: `service.create.{type}.own`, `service.update.{type}.own`, `service.delete.{type}.own`, `service.publish.{type}.own`
- Fires `VendorApprovedForType` event (after commit)
- Filament success notification: "Vendor approved for {type}" (in admin's current locale)

---

## Filament Action: ApproveVendorProfile (overall)

**Permission required**: `approve_vendor_profile`

**Confirmation required**: Yes

**Action calls**: `ApproveVendorAction::execute(VendorProfile $vendor)`

**Side effects**:
- Sets `vendor_profiles.approval_status = approved`
- Sets `approved_at = now()`, `approved_by = auth()->id()`
- Fires `VendorApproved` event (after commit)

---

## Filament Action: RejectVendorProfile

**Permission required**: `approve_vendor_profile`

**Form fields**:
```
rejection_reason: { "en": "...", "ar": "..." }  (Textarea with EN/AR tabs)
```

**Side effects**:
- Sets `approval_status = rejected`
- Sets `rejection_reason` JSON field
- Fires `VendorRejected` event (after commit)

---

## Filament Action: RevokeVendorType

**Permission required**: `revoke_vendor_type`

**Form fields**:
```
product_type: rental | sale | digital  (Select, required)
revoke_reason: JSON translatable (Textarea)
```

**Action calls**: `RevokeVendorTypeAction::execute(VendorProfile $vendor, ProductType $type, string $reason)`

**Side effects**:
- Sets `revoked_at = now()`, `revoked_by = auth()->id()` on matching row
- Revokes Spatie permissions for that type
- Fires `VendorTypeRevoked` event (after commit)

---

## API Endpoints (admin-facing — for future mobile admin, not Filament)

### GET /api/v1/admin/vendor-profiles

**Auth**: Required (admin role)
**Query**: `?status=pending&page=1`

**Response 200**:
```json
{
  "data": [
    {
      "id": "01IA...",
      "business_name": "Happy Balloons",
      "approval_status": "pending",
      "created_at": "2026-04-27T10:00:00Z",
      "approved_product_types": []
    }
  ],
  "meta": { "current_page": 1, "total": 42 },
  "errors": null
}
```

### POST /api/v1/admin/vendor-profiles/{public_id}/approve-for-type

**Auth**: Required (admin, permission: `approve_vendor_for_{type}`)

**Request body**:
```json
{ "product_type": "rental" }
```

**Response 200**:
```json
{
  "data": { "product_type": "rental", "approved_at": "2026-04-27T10:05:00Z" },
  "meta": {},
  "errors": null
}
```

**Errors**: `403` insufficient permission, `422` already approved for type
