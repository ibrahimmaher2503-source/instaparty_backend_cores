# Contract: Admin Decides a Service Change Request

**Feature**: `035-service-edit-approval`
**Surface**: Filament-backed admin UI at `/admin/pending-service-edits` plus three internal REST endpoints invoked by the page's Action buttons. The page is the primary UI; the endpoints are documented here for completeness and for any future automation.

## Authentication

Filament admin session. Admin user MUST have at least one of `service.moderate.rental`, `service.moderate.sale`, `service.moderate.digital`.

## Authorization

Each endpoint authorizes with `Gate::authorize('service.moderate.' . $service->product_type->value, $service)`.

---

## A. Approve

`POST /admin/service-change-requests/{publicId}/approve`

```json
{
  "admin_note": {
    "en": "Approved — price hike justified by supplier-cost screenshot",
    "ar": "تمت الموافقة — الزيادة مبررة بصورة من المورد"
  },
  "version": 3
}
```

Success: `200 OK`

```json
{
  "data": {
    "type": "service_change_request",
    "id": "01HTZ...",
    "status": "approved",
    "decided_by": {"id": "01HW...", "name": "Ibrahim"},
    "decided_at": "2026-05-16T11:55:02Z",
    "applied_fields": ["base_price_minor", "long_description.en", "long_description.ar", "service_rental_details.security_deposit_minor"]
  },
  "meta": {},
  "errors": []
}
```

Errors:
- `409 service_change_request.version_mismatch` — another admin already decided.
- `409 service_change_request.apply_failed` — referenced category/media missing.
- `403 service_change_request.permission_denied` — wrong product-type moderation permission.
- `404 service_change_request.not_found` — wrong publicId.

Side effects (in one transaction, in order):
1. `service_change_requests.status = 'approved'`, `decided_by`, `decided_at`, `admin_note`, `version += 1` set.
2. `services` row updated for shared fields.
3. `service_{type}_details` row updated for type-specific fields.
4. Media library `gallery` collection replayed per `gallery_ops`.
5. Availability windows, excluded dates, pricing tiers replayed.
6. One `audit_logs` row per changed field (subject = service).
7. After commit: `ServiceChangeRequestApproved` event fires → notification dispatch listener → Scout `Searchable` observer queues re-index.

---

## B. Reject

`POST /admin/service-change-requests/{publicId}/reject`

```json
{
  "admin_note": {
    "en": "Cover image violates content policy — please use a logo-free photo",
    "ar": "صورة الغلاف تنتهك سياسة المحتوى — يرجى استخدام صورة بدون شعار"
  },
  "version": 3
}
```

Success: `200 OK`

```json
{
  "data": {
    "type": "service_change_request",
    "id": "01HTZ...",
    "status": "rejected",
    "decided_by": {"id": "01HW...", "name": "Ibrahim"},
    "decided_at": "2026-05-16T12:01:09Z",
    "admin_note": {
      "en": "Cover image violates content policy — please use a logo-free photo",
      "ar": "صورة الغلاف تنتهك سياسة المحتوى — يرجى استخدام صورة بدون شعار"
    }
  },
  "meta": {},
  "errors": []
}
```

Validation errors:
- `422 admin_note.en required` — bilingual rule.
- `422 admin_note.ar required` — bilingual rule.

Side effects:
1. `service_change_requests.status = 'rejected'`, `decided_by`, `decided_at`, `admin_note`, `version += 1`.
2. Live `services` row unchanged.
3. `audit_logs` row written.
4. After commit: `ServiceChangeRequestRejected` event → vendor notification (push + email, bilingual).

---

## C. Request Clarification

`POST /admin/service-change-requests/{publicId}/request-clarification`

```json
{
  "admin_note": {
    "en": "Why are you raising the price 40% mid-season?",
    "ar": "لماذا ترفع السعر 40% في منتصف الموسم؟"
  },
  "version": 3
}
```

Success: `200 OK`

```json
{
  "data": {
    "type": "service_change_request",
    "id": "01HTZ...",
    "status": "awaiting_clarification",
    "clarification_round": 1,
    "decided_by": null,
    "decided_at": null,
    "messages": [
      {
        "author_role": "admin",
        "body": {"en": "...", "ar": "..."},
        "created_at": "2026-05-16T12:02:31Z"
      }
    ]
  },
  "meta": {},
  "errors": []
}
```

Errors:
- `422 admin_note.en|ar required` — bilingual rule.
- `409 service_change_request.clarification_cap_reached` — `clarification_round >= 3`.

Side effects:
1. `status = 'awaiting_clarification'`, `clarification_round += 1`, `admin_note` overwritten with the latest question (kept in main row + appended to message thread), `version += 1`.
2. One row appended to `service_change_request_messages` with `author_role = 'admin'`.
3. `audit_logs` row written.
4. After commit: `ServiceChangeRequestClarificationRequested` event → vendor notification.

---

## Resource shape (shared)

All three endpoints return `ServiceChangeRequestResource`:

```php
/**
 * @response {
 *   "data": {
 *     "type": "service_change_request",
 *     "id": "01HTZ8K9XZ...",
 *     "service": {
 *       "id": "01HTZ...",
 *       "name": {"en": "Bouncy Castle XL", "ar": "قلعة نطاطة كبيرة"},
 *       "product_type": "rental"
 *     },
 *     "vendor": {"id": "01HW...", "display_name": {"en": "...", "ar": "..."}},
 *     "status": "pending",
 *     "clarification_round": 0,
 *     "field_count": 4,
 *     "items": [
 *       {
 *         "field_path": "base_price_minor",
 *         "field_classification": "shared",
 *         "before_value": 10000,
 *         "after_value": 12500
 *       },
 *       {
 *         "field_path": "name.en",
 *         "field_classification": "shared",
 *         "before_value": "Bouncy Castle XL",
 *         "after_value": "Bouncy Castle XL — Premium"
 *       }
 *     ],
 *     "vendor_note": {"en": "...", "ar": "..."},
 *     "admin_note": null,
 *     "messages": [],
 *     "submitted_at": "2026-05-16T11:42:13Z",
 *     "decided_at": null
 *   },
 *   "meta": {"version": 3, "open_lock_held": true},
 *   "errors": []
 * }
 */
```

---

## Bruno collection entries

- `docs/api/collections/admin/service-change-requests/approve.bru`
- `docs/api/collections/admin/service-change-requests/reject.bru`
- `docs/api/collections/admin/service-change-requests/request-clarification.bru`

## API registry entries

Add three rows to `.specify/memory/api-registry.md` under the admin section:

```
| POST /admin/service-change-requests/{publicId}/approve              | Admin | Approve a pending service change request                     | AdminServiceChangeRequestController@approve              | ServiceChangeRequestResource | 035-service-edit-approval |
| POST /admin/service-change-requests/{publicId}/reject               | Admin | Reject a pending service change request (bilingual reason)   | AdminServiceChangeRequestController@reject               | ServiceChangeRequestResource | 035-service-edit-approval |
| POST /admin/service-change-requests/{publicId}/request-clarification | Admin | Move request to awaiting_clarification with a bilingual ask | AdminServiceChangeRequestController@requestClarification | ServiceChangeRequestResource | 035-service-edit-approval |
```
