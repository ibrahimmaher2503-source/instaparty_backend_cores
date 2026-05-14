# Quickstart: Admin Changes-Requested Workflow

**Branch**: `020-admin-changes-request` | **Date**: 2026-05-04

## Prerequisites

- Phase 1.1 complete (vendor onboarding + approval queue working)
- Phase 8.0 in progress or complete (service moderation resources exist)
- Redis running (queue worker)
- `php artisan serve` running on port 8000

---

## Step 1: Run Migrations

```bash
php artisan migrate
```

This runs (in order via Shared module ServiceProvider):
1. `2026_05_04_000001_create_change_requests_table` — new Shared table
2. `2026_05_04_000002_create_change_request_items_table` — new Shared table
3. `2026_05_04_000003_add_changes_requested_to_vendor_profiles_approval_status` — alters Identity enum
4. `2026_05_04_000004_add_changes_requested_to_services_status` — alters Catalog enum

---

## Step 2: Seed Notification Templates

```bash
php artisan db:seed --class=ChangeRequestNotificationTemplateSeeder
```

Seeds two templates into `notification_templates`:
- `vendor.changes_requested` (push + email, EN + AR)
- `vendor.resubmitted` (push + email, EN + AR)

---

## Step 3: Regenerate Shield Permissions

```bash
php artisan shield:generate --all
```

New Filament actions generate new permissions. Assign to `admin` role in seeders or Filament.

---

## Step 4: Test the Full Vendor Cycle

### 4a. Admin requests changes on a vendor profile

```bash
# Using Bruno or curl:
POST /api/v1/admin/vendor-profiles/{publicId}/change-requests
Authorization: Bearer {admin_token}
Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000
Content-Type: application/json

{
  "items": [
    {
      "field_path": "documents.cr_document",
      "requested_change_en": "CR document is blurry",
      "requested_change_ar": "مستند السجل التجاري غير واضح"
    }
  ]
}
```

Expected: `201` — vendor profile `approval_status` → `changes_requested`.

### 4b. Vendor views change request

```bash
GET /api/v1/vendor/change-requests?status=open
Authorization: Bearer {vendor_token}
```

Expected: list with one open request, items with `item_status=pending`.

### 4c. Vendor resubmits

```bash
POST /api/v1/vendor/vendor-profiles/{publicId}/resubmit
Authorization: Bearer {vendor_token}
Idempotency-Key: 550e8400-e29b-41d4-a716-446655440001

{
  "addressed_item_ids": ["<item_public_id>"],
  "resubmit_notes": "Uploaded fresh CR scan"
}
```

Expected: `200` — vendor profile `approval_status` → `pending`, change request `status` → `resubmitted`.

### 4d. Admin approves vendor

```bash
# Existing ApproveVendorProfileAction endpoint
POST /api/v1/admin/vendor-profiles/{publicId}/approve
```

Expected: vendor profile `approval_status` → `approved`, change request `status` → `resolved`.

---

## Step 5: Test 3-Cycle Limit

Run the cycle test Pest suite:

```bash
./vendor/bin/pest --filter=VendorChangesRequestedTest --group=vendor-changes
./vendor/bin/pest --filter=ServiceChangesRequestedTest --group=service-changes
```

Or run the full test suite with bail on first failure:

```bash
./vendor/bin/pest --bail
```

---

## Step 6: Test in Filament Admin

1. Log in at `/admin`
2. Navigate to **Vendor Approval Queue** → open a vendor in `pending` status
3. Click **"Request Changes"** → add at least 1 item in EN + AR → submit
4. Verify vendor profile shows `changes_requested` badge
5. Use the vendor API token to resubmit (or use a factory helper in Tinker)
6. Vendor profile returns to `pending` in the queue
7. For the 3-cycle test: repeat steps 3–6 three times, then verify **"Force Reject"** button appears and **"Request Changes"** is disabled

---

## Common Issues

| Symptom | Cause | Fix |
|---|---|---|
| `409 Conflict` on POST change-requests | Duplicate `Idempotency-Key` within 24h | Use a new UUID key |
| `422 cycle limit reached` | `cycle_number` already at 3 | Use "Force Reject" button instead |
| `422 validation` on empty AR text | Both `requested_change_en` and `requested_change_ar` are required | Fill both fields |
| Change request `status` stuck on `open` after approve | Listener not firing | Check queue worker is running: `php artisan queue:work` |
| Shield permission error on "Request Changes" | New permissions not generated | Run `php artisan shield:generate --all` |
