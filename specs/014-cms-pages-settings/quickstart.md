# Quickstart: CMS Pages + Settings (Phase 6.2)

> Concise implementation guide for `/speckit.implement` — files to create, commands to run.

---

## Module: `app/Modules/Shared/`

Phase 6.2 adds CMS/Settings to the existing `Shared` module. The Shared module's `ServiceProvider` must already exist from Phase 0.

---

## File Manifest (all new files)

```
app/Modules/Shared/
├── Domain/
│   ├── Enums/
│   │   └── CmsSlug.php                          [NEW]
│   └── Models/
│       ├── CmsPage.php                          [NEW]
│       ├── AppSetting.php                       [NEW]
│       └── FeatureFlag.php                      [NEW]
├── Application/
│   └── Actions/
│       ├── PublishCmsPageAction.php             [NEW]
│       └── UnpublishCmsPageAction.php           [NEW]
├── Http/
│   ├── Controllers/
│   │   └── CmsPageController.php               [NEW]
│   └── Resources/
│       └── CmsPageResource.php                 [NEW]
├── Filament/
│   └── Resources/
│       └── CmsPageResource.php                 [NEW — Filament, different from API Resource]
│   └── Pages/
│       └── ManageSettings.php                  [NEW]
├── Routes/
│   └── customer.php                            [NEW or APPEND — add CMS route]
└── Database/
    ├── Migrations/
    │   ├── xxxx_create_cms_pages_table.php      [NEW]
    │   ├── xxxx_create_app_settings_table.php   [NEW]
    │   └── xxxx_create_feature_flags_table.php  [NEW]
    └── Seeders/
        ├── CmsPagesSeeder.php                   [NEW]
        └── AppSettingsSeeder.php                [NEW]

tests/
└── Feature/
    └── Modules/
        └── Shared/
            └── CmsPageApiTest.php               [NEW]
```

---

## Key Commands After Implementation

```bash
# Apply migrations
php artisan migrate

# Run seeders (CMS pages + settings)
php artisan db:seed --class=CmsPagesSeeder
php artisan db:seed --class=AppSettingsSeeder

# Register Filament permissions
php artisan shield:generate --all

# Run tests
./vendor/bin/pest tests/Feature/Modules/Shared/CmsPageApiTest.php

# Run all tests to check for regressions
./vendor/bin/pest --bail
```

---

## Shared ServiceProvider Changes

In `app/Modules/Shared/Providers/SharedServiceProvider.php`, add (if not already):

```php
// boot()
$this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
$this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'shared');

// In Routes boot (add the customer route file):
Route::middleware(['api', 'locale'])
    ->prefix('api/v1')
    ->group(__DIR__ . '/../Routes/customer.php');
```

---

## Critical Invariants to Enforce

1. **Never expose draft pages**: `CmsPage::published()->where('slug', ...)` — never `CmsPage::where('slug', ...)` without the published scope on the public API.
2. **Both locales required on publish**: `PublishCmsPageAction` throws a validation exception if `$page->getTranslation('body', 'en')` or `$page->getTranslation('body', 'ar')` is empty.
3. **No money columns**: CMS has no financial data. No `Brick\Money` casts needed.
4. **No product type awareness**: No `match($enum)` for ProductType needed. CMS is purely cross-cutting.
5. **`updated_by` on every write**: Every `DB::transaction` closure must set `updated_by = auth()->id()` before saving.
6. **TipTap profile**: Use `->profile('default')` on all TiptapEditor fields in the Filament CmsPageResource.

---

## Test Checklist (must all be green)

| Test | Method | Asserts |
|---|---|---|
| Published page returns 200 in EN | `GET /api/v1/cms/pages/terms` with `Accept-Language: en` | 200, `data.title` = EN string |
| Published page returns 200 in AR | Same with `Accept-Language: ar` | 200, `data.title` = AR string |
| Unpublished page returns 404 | Create draft, call endpoint | 404 |
| Unknown slug returns 404 | `GET /api/v1/cms/pages/unknown` | 404 |
| Publish action sets is_published + published_at | Call `PublishCmsPageAction` | DB assertions |
| Publish fails if EN body empty | Call action with empty EN body | ValidationException |
| Publish fails if AR body empty | Call action with empty AR body | ValidationException |
| Unpublish sets is_published=false | Call `UnpublishCmsPageAction` | DB assertion, API returns 404 |
