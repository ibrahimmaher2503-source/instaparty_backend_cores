# Data Model: Phase 0 — Foundation

**Date**: 2026-04-26
**Scope**: Geography module entities + MoneyCast shared cast. Identity tables are schema-only in this phase (no model behavior documented here beyond basic FK references).

---

## 1. Governorate

**Table**: `governorates`
**Module**: `app/Modules/Geography/`
**Soft delete**: No

### Fields

| Column | PHP type | Cast / Notes |
|---|---|---|
| `id` | `int` | BIGINT UNSIGNED PK |
| `public_id` | `string` | CHAR(26) ULID — exposed in API URLs |
| `name` | `array` | JSON `{"en":"...","ar":"..."}` — `$translatable = ['name']` |
| `code` | `string` | VARCHAR(20) UNIQUE — e.g., `EG-C` for Cairo |
| `country_code` | `string` | CHAR(2) ISO-3166-1 alpha-2, default `'EG'` |
| `is_active` | `bool` | Default `true` |
| `sort_order` | `int` | UNSIGNED, default `0` |
| `created_at` | `Carbon` | |
| `updated_at` | `Carbon` | |

### Relationships

- `hasMany(Region::class, 'governorate_id')` — a governorate has many regions
- `hasMany(City::class, 'governorate_id')` — denormalized shortcut for fast city-by-governorate queries

### Scopes

- `scopeActive($query)` — filters `is_active = true`

### Validation rules (Filament form)

- `name` (EN): required, string, max 160
- `name` (AR): required, string, max 160
- `code`: required, string, max 20, unique on `governorates`
- `country_code`: required, string, size 2
- `sort_order`: integer, min 0

---

## 2. Region

**Table**: `regions`
**Module**: `app/Modules/Geography/`
**Soft delete**: No

### Fields

| Column | PHP type | Cast / Notes |
|---|---|---|
| `id` | `int` | BIGINT UNSIGNED PK |
| `public_id` | `string` | CHAR(26) ULID |
| `governorate_id` | `int` | FK → `governorates.id` `restrictOnDelete` |
| `name` | `array` | JSON translatable |
| `is_active` | `bool` | Default `true` |
| `sort_order` | `int` | UNSIGNED, default `0` |
| `created_at` | `Carbon` | |
| `updated_at` | `Carbon` | |

### Relationships

- `belongsTo(Governorate::class)`
- `hasMany(City::class, 'region_id')`

### Scopes

- `scopeActive($query)` — filters `is_active = true`
- `scopeForGovernorate($query, int $governorateId)` — filters by governorate

### Validation rules (Filament form)

- `name` (EN + AR): required, string, max 160
- `governorate_id`: required, exists in `governorates`
- `sort_order`: integer, min 0

---

## 3. City

**Table**: `cities`
**Module**: `app/Modules/Geography/`
**Soft delete**: No

### Fields

| Column | PHP type | Cast / Notes |
|---|---|---|
| `id` | `int` | BIGINT UNSIGNED PK |
| `public_id` | `string` | CHAR(26) ULID |
| `region_id` | `int` | FK → `regions.id` `restrictOnDelete` |
| `governorate_id` | `int` | FK → `governorates.id` `restrictOnDelete` — denormalized |
| `name` | `array` | JSON translatable |
| `latitude` | `float\|null` | DECIMAL(10,7) — centroid, nullable |
| `longitude` | `float\|null` | DECIMAL(10,7) — centroid, nullable |
| `is_active` | `bool` | Default `true` |
| `sort_order` | `int` | UNSIGNED, default `0` |
| `created_at` | `Carbon` | |
| `updated_at` | `Carbon` | |

### Relationships

- `belongsTo(Region::class)`
- `belongsTo(Governorate::class)` — uses denormalized `governorate_id`

### Scopes

- `scopeActive($query)` — filters `is_active = true`
- `scopeForGovernorate($query, int $governorateId)`
- `scopeForRegion($query, int $regionId)`

### Validation rules (Filament form)

- `name` (EN + AR): required, string, max 160
- `region_id`: required, exists in `regions`
- `governorate_id`: set automatically from the selected region (not user-editable)
- `latitude`, `longitude`: nullable, numeric, latitude between -90 and 90, longitude between -180 and 180
- `sort_order`: integer, min 0

### Denormalization invariant

`cities.governorate_id` MUST always equal `regions.governorate_id` for the city's region. This is enforced in the `CreateCityAction` / `UpdateCityAction` (Phase 1+). In Phase 0, the EgyptGeographySeeder sets it correctly. If the Filament Resource allows region selection, it MUST auto-populate `governorate_id` from the selected region via a `->afterStateUpdated()` callback on the `region_id` Select field.

---

## 4. MoneyCast (Shared)

**Class**: `App\Modules\Shared\Domain\Casts\MoneyCast`
**Type**: Laravel Eloquent cast implementing `CastsAttributes`
**Not a DB entity** — a reusable cast for all money columns in all modules.

### Contract

```php
// Declaration on a model:
protected function casts(): array
{
    return [
        'base_price' => MoneyCast::class . ':base_price_minor,base_price_currency',
    ];
}

// get() — reads from DB:
// Input:  $model->base_price_minor = 5000, $model->base_price_currency = 'EGP'
// Output: Brick\Money\Money::ofMinor(5000, 'EGP')

// set() — writes to DB:
// Input:  $model->base_price = Money::ofMinor(5000, 'EGP')
// Output: ['base_price_minor' => 5000, 'base_price_currency' => 'EGP']
```

### Rules

- Input to `set()` can be `Brick\Money\Money` or `int` (integer treated as minor units with the model's current currency).
- Never stores `float` — only reads/writes BIGINT minor units.
- Throws `InvalidArgumentException` if `set()` receives a float.
- Cast key MUST match the column name prefix (the part before `_minor`).

---

## 5. Identity tables (schema reference only)

The following tables are created in Phase 0 as schema only. Their Eloquent models, factories, and Filament Resources are built in Phase 1. They are listed here as dependency reference.

| Table | Depends on |
|---|---|
| `vendor_profiles` | `users`, `governorates`, `cities` |
| `vendor_documents` | `vendor_profiles` |
| `vendor_approved_product_types` | `vendor_profiles`, `users` |
| `vendor_business_hours` | `vendor_profiles` |
| `vendor_coverage_areas` | `vendor_profiles`, `cities` |
| `customer_profiles` | `users` |
| `customer_addresses` | `users`, `cities` |
| `user_devices` | `users` |
| `two_factor_secrets` | `users` |

All FK references to `cities` use `restrictOnDelete` — verified by `MigrationSmokeTest`.

---

## State transitions

Phase 0 has no state machines. The first state machine (booking lifecycle) arrives in Phase 3.

---

## Entity relationship summary (Phase 0)

```
governorates
  └── regions (governorate_id FK)
        └── cities (region_id FK, governorate_id denorm)
                └── vendor_coverage_areas.city_id (Phase 1 model)
                └── customer_addresses.city_id (Phase 1 model)
                └── vendor_profiles.primary_city_id (Phase 1 model)
```
