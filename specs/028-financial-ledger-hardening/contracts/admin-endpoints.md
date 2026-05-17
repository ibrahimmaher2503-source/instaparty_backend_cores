# Contract: Admin HTTP Endpoints

**Module**: `app/Modules/Settlement/`
**Route file**: `app/Modules/Settlement/Routes/admin.php`
**Middleware**: `web`, `auth:sanctum`, `role:admin`, `permission:audit.view`

Per Constitution §"API DOCUMENTATION CONSTRAINT", every endpoint here includes Scribe-compatible PHPDoc on the FormRequest and Resource. Bruno collection entries are added at `docs/api/collections/admin/reconciliation.bru`.

---

## 1. `GET /admin/reconciliation/status`

Returns the latest reconciliation run summary and the most recent unresolved findings.

**Auth**: admin role, permission `audit.view`.
**Idempotency**: none (read-only).
**Response shape** (standard `ApiResponse` envelope):

```json
{
  "data": {
    "latest_run": {
      "public_id": "01J...",
      "scope_type": "recent_touch",
      "status": "clean",
      "wallets_scanned": 142,
      "findings_count": 0,
      "auto_repaired_count": 0,
      "manual_review_count": 0,
      "started_at": "2026-05-15T07:00:00Z",
      "completed_at": "2026-05-15T07:01:23Z"
    },
    "unresolved_findings_count": 0,
    "recent_findings": [
      {
        "public_id": "01J...",
        "finding_type": "wallet_cache_drift",
        "severity": "warning",
        "resource_type": "wallet",
        "resource_id": 42,
        "expected": {"balance_minor": 1234500},
        "actual": {"balance_minor": 1234400},
        "delta": {"balance_minor": 100},
        "resolution": "auto_repaired",
        "resolved_at": "2026-05-15T07:00:48Z",
        "description": "Wallet cache was 100 piastres below the projected balance and was auto-repaired."
      }
    ]
  },
  "meta": {
    "locale": "en"
  },
  "errors": null
}
```

The `description` is locale-converted at the Resource layer (`App::getLocale()`).

---

## 2. `POST /admin/reconciliation/trigger`

Enqueues a reconciliation run with optional scope.

**Auth**: admin role, permission `audit.view`.
**Idempotency**: REQUIRED — `Idempotency-Key` header. 24h window. Same key + same payload returns the original 202 response; same key + different payload returns 409.

**Request body** (`TriggerReconciliationRequest`):

```php
/**
 * @bodyParam scope_type string required Reconciliation scope: all|wallet|vendor|date_range. Example: all
 * @bodyParam wallet_id integer Required when scope_type=wallet. Example: 42
 * @bodyParam vendor_id integer Required when scope_type=vendor. Example: 17
 * @bodyParam date_from string Required when scope_type=date_range. ISO-8601 date. Example: 2026-05-01
 * @bodyParam date_to string Required when scope_type=date_range. ISO-8601 date. Example: 2026-05-15
 */
```

Validation:
- `scope_type` must be one of `all|wallet|vendor|date_range`.
- Conditional `required_if` rules for the other fields.
- `date_to >= date_from` and the range cannot exceed 90 days.

**Response** (202 Accepted):

```json
{
  "data": {
    "run_public_id": "01J...",
    "status": "queued",
    "scope_type": "all",
    "estimated_duration_seconds": 240
  },
  "meta": {
    "locale": "en"
  },
  "errors": null
}
```

**Error responses**:
- `400` — invalid scope params.
- `403` — missing role or permission.
- `409` — same idempotency key, different payload, OR a run with the same scope is already in progress.
- `422` — validation errors.

---

## API registry entries

Both endpoints get an entry in `.specify/memory/api-registry.md`:

```markdown
| Method | Path | Module | Auth | Idempotency | Locale | Bruno |
|---|---|---|---|---|---|---|
| GET | /admin/reconciliation/status | Settlement | admin role + audit.view | none | EN/AR | admin/reconciliation.bru |
| POST | /admin/reconciliation/trigger | Settlement | admin role + audit.view | required | EN/AR | admin/reconciliation.bru |
```

## Bruno collection

`docs/api/collections/admin/reconciliation.bru` contains two requests:
- `GET status` with header `Accept-Language: ar` to verify AR locale.
- `POST trigger` with body `{ "scope_type": "all" }` and `Idempotency-Key: 01J-test-key`.

---

## Not added in this feature (Phase 2 candidates)

- `GET /admin/reconciliation/runs/{id}` — detail view of a run (Filament covers this for now).
- `GET /admin/reconciliation/findings/{id}` — detail view of a finding (Filament covers).
- `POST /admin/reconciliation/findings/{id}/resolve` — admin-resolve an unresolved finding.
- Customer-facing or vendor-facing endpoints — explicitly out of scope per `spec.md` § "Out of Scope".
