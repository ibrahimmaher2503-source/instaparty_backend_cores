# Catalog Module — API + Admin UI Smoke (2026-05-04)

**Tester:** Claude (driven by Ibrahim) · **Branch:** `018-subscriptions-tiers` · **Server:** `php artisan serve` on `127.0.0.1:8000` · **Browser:** chrome-devtools-mcp

## Summary

| Area | Result |
|---|---|
| Admin login (Filament) | ✅ PASS |
| Sidebar navigation renders all modules | ✅ PASS |
| Catalog list pages (Categories / Occasions / Themes / Rental / Sale / Digital) | ✅ render |
| Public Catalog APIs (`/api/v1/customer/categories,occasions`) | ✅ 200 with EN+AR JSON |
| `/api/v1/customer/services` discovery endpoint | ❌ 500 — see Defect 3 |
| Service create/edit forms (rental/sale/digital) | ❌ 500 — see Defect 1 |
| Categories list extra render | ❌ logged 500 — see Defect 2 |

---

## Setup tweaks made to get local stack running

These should be considered for `.env.example` so the next dev doesn't hit them.

| Var | Was | Set to | Why |
|---|---|---|---|
| `TELESCOPE_ENABLED` | (unset → defaults true) | `false` | `telescope_entries` table not migrated; terminate handler crashed on every request |
| `CACHE_STORE` | `database` | `file` | SQLite locked under concurrent Filament/Livewire writes |
| `SESSION_DRIVER` | `database` | `file` | Same SQLite contention; rate-limiter cache writes deadlocked logins |
| `SCOUT_DRIVER` | `meilisearch` | `database` | `meilisearch/meilisearch-php` package not installed locally; Discovery endpoint hard-failed |

Admin user seeded via `php artisan db:seed --class="Database\Seeders\AdminUserSeeder"` →  
**`admin@instaparty.local` / `password`** (from `config('app.admin_password', 'password')`).

---

## Defect 1 — Service Filament resources crash on create/edit (P1)

**Symptom:** Navigating to `/admin/{rental|sale|digital}-services/create` or any edit page returns HTTP 500.

**Stack:**
```
TypeError: Filament\Forms\Components\Component::label():
  Argument #1 ($label) must be of type Htmlable|Closure|string|null, array given
  at app/Modules/Catalog/Filament/Resources/RentalServiceResource.php:112
```

Same TypeError fires on `RentalServiceResource.php:191`, `SaleServiceResource.php:190`, `DigitalServiceResource.php:194`.

**Root cause:**  
`__('catalog.status')` resolves to an **array**:
```php
// app/Modules/Catalog/Resources/lang/en/catalog.php
'status' => [
    'draft' => 'Draft',
    'pending_review' => 'Pending Review',
    // ...
],
```
The Filament call sites assume it's a string label.

**Fix options (pick one):**
1. Add a sibling key `'status_label' => 'Status'` (and `'name_label'`, `'code_label'`, etc.) and update the Filament calls.
2. Rename existing array keys to `'statuses' => [...]` and add `'status' => 'Status'`.
3. Hardcode the label inline: `->label('Status')` (drops i18n).

Option 2 is cleanest — single source of truth for the field-name labels.

Repeat the audit across the catalog Resources — every `__('catalog.X')` whose target is a sub-keyed array will TypeError the moment that page is hit.

---

## Defect 2 — `CategoryResource` table column closure can return array (P2)

**Stack:**
```
ViewException: CategoryResource::{closure:table():146}():
  Return value must be of type string, array returned
  at CategoryResource.php:146
```

```php
TextColumn::make('name')
    ->getStateUsing(fn (Category $record): string =>
        $record->getTranslation('name', app()->getLocale(), useFallbackLocale: true))
```

`getTranslation()` on a translatable JSON column can return the raw array if the locale row is genuinely missing AND fallback is also missing/array. Wrap with `(string)` cast or use `Arr::get` on a known fallback locale.

---

## Defect 3 — `/api/v1/customer/services` 500s on null `product_type` (P1)

```
ErrorException: Attempt to read property "value" on null
  at app/Modules/Catalog/Domain/Models/Service.php:163
  in toSearchableArray() called from Scout DatabaseEngine
```

```php
'product_type' => $this->product_type->value,   // Service.php:163
```

Either:
- a Service row exists with `product_type = NULL` (data integrity violation — column should be NOT NULL per the migration), or
- the `ProductType::class` cast returned `null` because the stored string isn't a valid enum case.

Quick guard: `$this->product_type?->value` (and same for `status->value` on line 169). Better: also write a data-integrity check in the seeder/factory chain.

---

## Defect 4 — Customer Catalog endpoints require auth (design question, not a bug)

`GET /api/v1/customer/categories` and `…/occasions` return **401** without a token.

Current behavior:
```bash
curl /api/v1/customer/categories                           # → 401
curl -H "Authorization: Bearer <token>" /api/v1/customer/categories   # → 200
```

Spec PRD §customer-flow lists category browsing as part of the **anonymous** discovery flow. If the intent is anonymous browsing, move these routes out of the `auth:sanctum` group (or into a `/api/v1/public/...` prefix like `…/services/{id}/reviews` already uses).

**Need product call** — if it's deliberate, add a comment in `Routes/customer.php`; if not, it's a P2.

---

## Defect 5 — Bilingual API responses always return both locales (design question)

EN and AR responses to `/customer/categories` are byte-identical — both return:
```json
{"name": {"en": "General Wedding", "ar": "عام زفاف"}, ...}
```

`Accept-Language: ar-EG` does NOT cause the API to return a locale-resolved string.

This is a defensible choice (let the client pick the locale), but `04_Bilingual_Spec.md` should pin which one is the contract. Either:
- **A.** Always return full `{en, ar}` dicts (current behavior — document it).
- **B.** Resolve to a string per `Accept-Language` and add a `?include_translations=true` opt-in for editors.

---

## What was NOT tested (out of scope / not requested)

- Vendor-side Catalog mutations (`POST /api/v1/vendor/services/{type}`) — would require a vendor-role token + a category seeded.
- Excel imports (`/admin/import-{type}-services-page`)
- Service inventory reservations
- Category field schemas
- Subscription gates on Catalog actions (Phase 3 work, not part of this smoke)

---

## Screenshots

`docs/reviews/catalog-ui-screens/`:

| File | Page |
|---|---|
| `01-dashboard.png` | Filament dashboard after login |
| `02-categories.png` | Categories list (renders; row interaction may hit Defect 2) |
| `03-occasions.png` | Occasions list |
| `04-rental-services.png` | Rental Services list |
| `05-sale-services.png` | Sale Services list |
| `06-digital-services.png` | Digital Services list |
| `07-service-themes.png` | Service Themes list |
| `08-rental-create-form.png` | Rental Service create — shows Filament error overlay (Defect 1) |

---

## Recommended next actions

1. **Fix Defect 1** — Catalog Filament `__('catalog.status')` calls. ~15 min including locale file rename + grep audit of all callers.
2. **Fix Defect 3** — `Service::toSearchableArray` null-safe operator. 1-line change + investigate why a NULL `product_type` slipped through.
3. **Decide Defect 4** — anonymous browsing or token-required? Update routes accordingly.
4. **Decide Defect 5** — single contract for bilingual API responses, document in `04_Bilingual_Spec.md`.
5. **Update `.env.example`** with the four var changes above so dev setup doesn't trip the next person.
