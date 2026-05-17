# Contract: Vendor Submits a Material Edit

**Feature**: `035-service-edit-approval`
**Surface**: Internal HTTP route used by the existing vendor portal. No new public-API URL is added in Phase 1; this contract documents the existing `PUT /api/v1/vendor/services/{servicePublicId}` endpoint's **new branching behavior** when the service is `published` and the edit touches any material field.

## Authentication

Sanctum SPA session cookie (Next.js vendor portal). Vendor user MUST own the `vendor_profile_id` that owns the service.

## Authorization

`Gate::authorize('update', $service)` — existing policy. Internally the controller calls `DetectMaterialServiceChangesAction`; no new permission needed at the route level. Per-product-type vendor permissions (`service.update.{rental|sale|digital}.own`) continue to apply.

## Request

`PUT /api/v1/vendor/services/{servicePublicId}`

Body is **unchanged from the existing per-type update endpoint** — vendors send a full payload mirroring the create endpoint. Examples:

```json
{
  "name": {"en": "Bouncy Castle XL", "ar": "قلعة نطاطة كبيرة"},
  "long_description": {"en": "...", "ar": "..."},
  "base_price_minor": 12500,
  "base_price_currency": "EGP",
  "category_id": "01HTZ...",
  "gallery_ops": [
    {"op": "add", "media_id": 4711},
    {"op": "remove", "media_id": 4500}
  ],
  "details": {
    "security_deposit_minor": 50000,
    "default_rental_duration_hours": 6
  },
  "vendor_note": {
    "en": "Cost of inflatables went up — adjusting for the season.",
    "ar": "ارتفعت تكلفة القلاع — أقوم بتعديل السعر للموسم."
  }
}
```

### @bodyParam excerpt (Scribe format)

```php
/**
 * @bodyParam name.en string The English service name. Example: Bouncy Castle XL
 * @bodyParam name.ar string The Arabic service name. Example: قلعة نطاطة كبيرة
 * @bodyParam base_price_minor integer Price in minor units. Example: 12500
 * @bodyParam base_price_currency string ISO 4217 currency. Example: EGP
 * @bodyParam vendor_note.en string Optional note shown to admin. Required if vendor_note.ar present. Example: Adjusting for season
 * @bodyParam vendor_note.ar string Optional note shown to admin. Required if vendor_note.en present. Example: تعديل موسمي
 * ...
 */
```

## Response — three branches

### Branch A: service is NOT `published`

Existing behavior — fields apply directly, `200 OK` with the updated `ServiceResource`.

### Branch B: service IS `published` AND no material field changed

Non-material edit. Fields apply directly. `200 OK` with the updated `ServiceResource`. No `service_change_request` row created.

### Branch C: service IS `published` AND at least one material field changed

New behavior. The live `services` row is **untouched**. A new `service_change_request` is created.

```
HTTP/1.1 202 Accepted
Content-Type: application/json
```

```json
{
  "data": {
    "type": "service_change_request",
    "id": "01HTZ8K9XZ...",
    "status": "pending",
    "submitted_at": "2026-05-16T11:42:13Z",
    "field_count": 4,
    "vendor_note": {
      "en": "Cost of inflatables went up — adjusting for the season.",
      "ar": "ارتفعت تكلفة القلاع — أقوم بتعديل السعر للموسم."
    }
  },
  "meta": {"product_type": "rental"},
  "errors": []
}
```

### Branch D: service IS `published`, material change attempted, but a pending request already exists

```
HTTP/1.1 409 Conflict
```

```json
{
  "data": null,
  "meta": {},
  "errors": [
    {
      "code": "service_edit.pending_request_exists",
      "title": "You already have a pending edit awaiting admin review",
      "title_ar": "لديك تعديل قيد المراجعة من قبل الإدارة",
      "open_request_public_id": "01HTZ8...",
      "open_request_status": "pending"
    }
  ]
}
```

## Side effects

- **Branch C** writes one row in `service_change_requests`, N rows in `service_change_request_items`, and one `audit_logs` row.
- **Branch C** fires `App\Modules\Catalog\Domain\Events\ServiceChangeRequestSubmitted` via `DB::afterCommit`.
- **Branch C** does NOT mutate `services` or any detail row. Live discovery is preserved.

## Validation errors

| Code | Trigger | HTTP |
|---|---|---|
| `validation` | Missing required field, locale mismatch, etc. | 422 |
| `service_edit.pending_request_exists` | One already open | 409 |
| `service_edit.service_locked` | Service in `archived` or `suspended` state | 409 |

## Bruno collection entry

Path: `docs/api/collections/vendor/services/update-service.bru`. Must add a new example response set for Branch C and Branch D.

## API registry entry

Add to `.specify/memory/api-registry.md`:

```
| PUT /api/v1/vendor/services/{publicId} | Vendor | Update service (now staged on published services with material changes) | ServiceController@update | ServiceResource OR ServiceChangeRequestResource | 035-service-edit-approval |
```

---

# Companion: Vendor reply to clarification

`POST /api/v1/vendor/service-change-requests/{publicId}/reply`

```json
{
  "body": {"en": "I raised it because supplier cost rose 18%", "ar": "رفعت السعر لأن تكلفة المورد ارتفعت 18%"}
}
```

Response: `200 OK` with the updated `ServiceChangeRequestResource` showing `status=pending` and the new message in the thread.

Errors:
- 404 if not the vendor's request
- 409 if status is not `awaiting_clarification`
- 422 if `body.en` or `body.ar` missing

# Companion: Vendor cancels a pending edit

`DELETE /api/v1/vendor/service-change-requests/{publicId}`

Response: `200 OK`. Sets status to `cancelled_vendor_suspended`? No — vendor-initiated cancel uses a distinct status `cancelled_by_vendor`. *(Update — added to enum in data-model.md if implemented; deferred to Phase 1.5 per cut-list. NOT IN SCOPE for this feature.)*

> ⚠️ Vendor-initiated cancel is **deferred**. In Phase 1, the only way to clear a pending request is admin approve/reject or clarification reply.
