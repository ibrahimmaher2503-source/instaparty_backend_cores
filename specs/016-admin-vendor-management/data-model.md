# Data Model: Phase 6.4 — Admin Vendor Management

**Status**: Complete
**Date**: 2026-05-03

No new tables or migrations. All entities below are existing schema tables (locked, `docs/specs/11_DB_Schema.md`).

---

## Entities Read or Written by This Feature

### `vendor_profiles` (Identity module — primary entity)

| Column | Access | Notes |
|--------|--------|-------|
| `business_name` | READ + WRITE | JSON translatable — edit form (EN/AR tabs) |
| `bio` | READ + WRITE | JSON translatable |
| `business_type` | READ + WRITE | ENUM select |
| `primary_governorate_id` | READ + WRITE | Cascading select |
| `primary_city_id` | READ + WRITE | Cascading select (filtered by governorate) |
| `address_line` | READ + WRITE | JSON translatable |
| `commercial_register_no`, `tax_id`, `national_id` | READ + WRITE | Text inputs |
| `bank_holder_name`, `bank_iban`, `bank_swift`, `bank_name` | READ + WRITE | Bank section (activity-logged) |
| `approval_status` | READ ONLY | Displayed as badge; NOT writable via edit form |
| `approved_at`, `approved_by` | READ ONLY | Displayed in infolist |
| `rating_avg`, `rating_count` | READ ONLY | Displayed in overview tab |
| `slug` | READ ONLY | Locked after creation |

### `vendor_documents` (Identity module)

| Column | Access | Notes |
|--------|--------|-------|
| `doc_type` | READ ONLY | Display which document type |
| `file_path` | READ + WRITE | Updated on re-upload (new S3 key replaces old) |
| `file_name` | READ + WRITE | Updated on re-upload |
| `status` | READ ONLY | Displayed as badge |
| `review_notes` | READ ONLY | Displayed |

### `vendor_business_hours` (Identity module)

| Column | Access | Notes |
|--------|--------|-------|
| `day_of_week` | READ + WRITE | 0=Sun..6=Sat — Repeater rows |
| `opens_at` | READ + WRITE | TimePicker |
| `closes_at` | READ + WRITE | TimePicker |

Full replace via `UpsertVendorBusinessHoursAction` (delete-all + reinsert all 7 rows in one transaction).

### `vendor_coverage_areas` (Identity module)

| Column | Access | Notes |
|--------|--------|-------|
| `city_id` | READ + WRITE | CheckboxList of cities (grouped by governorate) |
| `delivery_fee_minor` | READ + WRITE | Per-city delivery fee (TextInput with money suffix) |
| `delivery_fee_currency` | WRITE | Hardcoded `'EGP'` for Phase 1 |
| `min_order_minor` | READ + WRITE | Minimum order amount per city |
| `min_order_currency` | WRITE | Hardcoded `'EGP'` |

Full replace via new `AdminReplaceVendorCoverageAction`.

### `vendor_approved_product_types` (Identity module)

| Column | Access | Notes |
|--------|--------|-------|
| `product_type` | READ ONLY | Displayed in tabs |
| `approved_at`, `approved_by` | READ ONLY | Infolist |
| `revoked_at`, `revoked_by` | READ ONLY (set by RevokeVendorTypeAction) | |
| `revoke_reason` | WRITE (via RevokeVendorTypeAction) | JSON translatable `{en, ar}` — mandatory |

### `activity_log` (spatie/laravel-activitylog table — Cross-cutting)

Written by: `UpdateVendorProfileAction`, `UpsertVendorBusinessHoursAction`, `AdminReplaceVendorCoverageAction`, `ImpersonateVendorAction`.

Read by: `ActivityLogRelationManager` (displayed in Activity tab).

### `audit_logs` (Cross-cutting — append-only)

Written by: `ImpersonateVendorAction` only (in this phase).

| Column | Value written |
|--------|--------------|
| `auditable_type` | `App\Modules\Identity\Domain\Models\VendorProfile` |
| `auditable_id` | `$vendorProfile->id` |
| `user_id` | `$admin->id` |
| `action` | `'vendor_impersonated'` |
| `changes` | `{"token_ability": "impersonation", "expires_minutes": 30}` |
| `ip_address` | `request()->ip()` |
| `user_agent` | `request()->userAgent()` |
| `created_at` | `now()` |

---

## New Action Classes

### `ImpersonateVendorAction`

```
Namespace: App\Modules\Identity\Application\Actions
Method: execute(VendorProfile $vendorProfile, User $adminUser): string
Returns: plain-text Sanctum token
Side effects:
  1. Writes to activity_log (spatie activitylog)
  2. Writes to audit_logs (direct insert, inside DB::transaction)
  3. Creates Sanctum personal_access_token with ability ['impersonation'], expires 30 min (DB::afterCommit)
```

### `AdminReplaceVendorCoverageAction`

```
Namespace: App\Modules\Identity\Application\Actions
Method: execute(VendorProfile $vendorProfile, array $areas): void
  $areas = [['city_id' => int, 'delivery_fee_minor' => int, 'min_order_minor' => int], ...]
Side effects:
  1. DB::transaction: delete all vendor_coverage_areas for vendor, reinsert all
  2. Writes to activity_log
```

---

## New Filament Classes

### `VendorProfileResource\Pages\EditVendorProfile`

Standard Filament `EditRecord` page. Delegates save to `UpdateVendorProfileAction`. Writes `activity_log` entry for ALL fields changed (not just sensitive). After save, `UpsertVendorBusinessHoursAction` and `AdminReplaceVendorCoverageAction` run within the same HTTP request.

### RelationManagers (read-only tabs)

| Class | Table queried | Tab label |
|-------|--------------|-----------|
| `ServicesRelationManager` | `services` WHERE `vendor_profile_id` | Services |
| `BookingVendorsRelationManager` | `booking_vendors` JOIN `bookings` | Bookings |
| `WalletRelationManager` | `wallets` + `wallet_ledger` | Wallet |
| `WithdrawalsRelationManager` | `withdrawals` | Withdrawals |
| `VendorReviewsRelationManager` | `vendor_reviews` | Reviews |
| `ActivityLogRelationManager` | `activity_log` WHERE `subject_type = VendorProfile AND subject_id` | Activity |

---

## New Permissions (Shield)

| Permission slug | Assigned to |
|----------------|-------------|
| `impersonate_vendor` | `super_admin` only |
| `manage_vendor_profile` | `admin`, `super_admin` (already generated by Shield from VendorProfileResource) |
| `re_upload_vendor_document` | `admin`, `super_admin` |

Run after implementation:
```bash
php artisan shield:generate --all
```

---

## No Migrations Required

All tables used exist in the locked schema (verified 2026-05-03 against `docs/specs/11_DB_Schema.md`):
- `vendor_profiles` ✅
- `vendor_documents` ✅
- `vendor_business_hours` ✅
- `vendor_coverage_areas` ✅
- `vendor_approved_product_types` with `revoke_reason JSON` column ✅
- `audit_logs` ✅ (created in Phase 6.0)
- `activity_log` ✅ (spatie/laravel-activitylog, from Phase 0.0)
