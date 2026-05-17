# Quickstart: Vendor Onboarding Checklist Widget

**Feature**: `034-vendor-onboarding-checklist`
**Audience**: a developer (Ibrahim or future collaborator) verifying the widget locally after the feature is implemented.

---

## 1. Pull and install

```powershell
git fetch
git checkout 034-vendor-onboarding-checklist
composer install
```

## 2. Reset database and seed

```powershell
php artisan migrate:fresh --seed
```

This runs the standard Phase 1 seeders (vendor categories, geographies, roles).

## 3. Seed five test vendors via Tinker

Open `php artisan tinker` and paste each of the snippets below (one per state). Use any password (e.g., `password`) for login.

### 3a. Empty vendor (only registered)

```php
$user = \App\Modules\Identity\Domain\Models\User::factory()->create([
    'email' => 'empty@vendor.test',
    'password' => bcrypt('password'),
]);
\App\Modules\Identity\Domain\Models\VendorProfile::factory()->for($user)->create([
    'business_name' => ['en' => 'Empty Vendor Co', 'ar' => 'متجر فارغ'],
    'approval_status' => 'pending',
    'bank_name' => null,
    'bank_iban' => null,
]);
```

### 3b. Partially-onboarded vendor (profile + docs uploaded, awaiting admin)

```php
$user = \App\Modules\Identity\Domain\Models\User::factory()->create(['email' => 'partial@vendor.test', 'password' => bcrypt('password')]);
$profile = \App\Modules\Identity\Domain\Models\VendorProfile::factory()->for($user)->create([
    'business_name' => ['en' => 'Partial Co', 'ar' => 'متجر جزئي'],
    'approval_status' => 'pending',
    'bank_name' => 'CIB',
    'bank_account_holder' => 'Partial Owner',
    'bank_iban' => 'EG380019000500000000123456789',
]);
\App\Modules\Identity\Domain\Models\VendorDocument::factory()->for($profile, 'vendorProfile')->create(['doc_type' => 'cr', 'status' => 'pending']);
\App\Modules\Identity\Domain\Models\VendorDocument::factory()->for($profile, 'vendorProfile')->create(['doc_type' => 'tax_card', 'status' => 'pending']);
```

### 3c. Fully-onboarded vendor (approved, with services)

```php
$user = \App\Modules\Identity\Domain\Models\User::factory()->create(['email' => 'full@vendor.test', 'password' => bcrypt('password')]);
$profile = \App\Modules\Identity\Domain\Models\VendorProfile::factory()->for($user)->create([
    'approval_status' => 'approved',
    'approved_at' => now(),
    'bank_name' => 'NBE',
    'bank_account_holder' => 'Full Owner',
    'bank_iban' => 'EG380019000500000000999999999',
]);
\App\Modules\Identity\Domain\Models\VendorBusinessHour::factory()->for($profile, 'vendorProfile')->create();
\App\Modules\Identity\Domain\Models\VendorCoverageArea::factory()->for($profile, 'vendorProfile')->create();
\App\Modules\Identity\Domain\Models\VendorApprovedProductType::factory()->for($profile, 'vendorProfile')->create(['product_type' => 'rental']);
\App\Modules\Catalog\Domain\Models\Service::factory()->for($profile, 'vendorProfile')->create(['status' => 'published']);
```

### 3d. Rejected vendor (with localised rejection reason)

```php
$user = \App\Modules\Identity\Domain\Models\User::factory()->create(['email' => 'rejected@vendor.test', 'password' => bcrypt('password')]);
\App\Modules\Identity\Domain\Models\VendorProfile::factory()->for($user)->create([
    'approval_status' => 'rejected',
    'rejected_at' => now(),
    'rejection_reason' => [
        'en' => 'Tax ID does not match commercial register.',
        'ar' => 'الرقم الضريبي لا يطابق السجل التجاري.',
    ],
]);
```

### 3e. Suspended vendor

```php
$user = \App\Modules\Identity\Domain\Models\User::factory()->create(['email' => 'suspended@vendor.test', 'password' => bcrypt('password')]);
\App\Modules\Identity\Domain\Models\VendorProfile::factory()->for($user)->create([
    'approval_status' => 'suspended',
    'suspended_at' => now(),
    'rejection_reason' => [
        'en' => 'Multiple unresolved customer complaints.',
        'ar' => 'شكاوى عملاء متعددة لم يتم حلها.',
    ],
]);
```

## 4. Visual verification

For each vendor, log in at `/vendor/login` and open `/vendor`. Confirm:

- **Empty**: only "Business profile started" is the success row. Progress ~10 %. Next recommended: "Complete business profile" → `VendorProfilePage`.
- **Partial**: profile + banking + docs-uploaded green; docs-approved info (pending review); approval-status info. Next recommended: "Add a coverage area".
- **Full**: every row green. No "Next recommended action" button — "Onboarding complete" chip instead. Approved product types sub-line lists "Rental".
- **Rejected**: red banner above the checklist with EN string. Switch locale to AR → banner shows Arabic string.
- **Suspended**: `CheckVendorSuspension` middleware redirects to `AccountSuspendedPage`. The page renders only the suspension banner (no checklist rows).

## 5. AR locale + RTL verification

For each vendor above, click the language switcher (top-right of the Filament panel) and select العربية. Confirm:

- All row labels are Arabic.
- The progress label is "اكتمل N من 10".
- RTL layout — status icons appear on the right side of each row.

## 6. Pest tests

```powershell
./vendor/bin/pest --filter=VendorOnboardingChecklistWidget
./vendor/bin/pest --filter=VendorOnboardingChecklistService
```

All tests must pass.

## 7. Static analysis

```powershell
./vendor/bin/phpstan analyse app/Modules/Identity
./vendor/bin/pint app/Modules/Identity
```

PHPStan: clean. Pint: no diffs.

## 8. Filament production cache

```powershell
php artisan filament:cache-components
```

Then re-load `/vendor` and confirm the widget still renders. If it fails, the widget's view path or namespace is misconfigured — see `app/Modules/Identity/Providers/IdentityServiceProvider.php` `loadViewsFrom` call.

---

## What "done" looks like

- All five test vendors render the widget as described in step 4.
- AR locale renders correctly for all five (step 5).
- Pest tests green (step 6).
- PHPStan + Pint clean (step 7).
- Production caching works (step 8).
