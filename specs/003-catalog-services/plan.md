> **RETROSPECTIVE** — generated after implementation for spec-kit precedent. Reflects code as shipped on branch `003-catalog-services` (merged to `master` via 631406e), not original planning intent. Every task in `tasks.md` is checked `[x]`.

# Implementation Plan: Catalog Service Management (Phases 2.0–2.4)

**Branch**: `003-catalog-services` | **Date**: 2026-04-29 (retro: 2026-05-03) | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `specs/003-catalog-services/spec.md`
**ADR**: [ADR-0004 — Catalog Module](../../docs/adr/0004-catalog-module.md) — Accepted

---

## Summary

Build the polymorphic-base + per-type-detail Service catalog for InstaParty across five micro-phases:

| Micro-phase | Scope |
|---|---|
| **2.0** | Catalog foundation — `occasions`, `categories` (hierarchical), `occasion_category` pivot, `category_field_schemas`, `service_themes`. No `services` yet. |
| **2.1** | Rental product type — `services` polymorphic base + `service_rental_details` (1:1) + `RentalServiceResource` (API + Filament). |
| **2.2** | Sale product type — `service_sale_details` (1:1) + `SaleServiceResource`; conditional validation `lead_time_hours` required when `is_made_to_order=true`. |
| **2.3** | Digital product type + inventory reservations — `service_digital_details` (1:1), `service_inventory_reservations` (cart 15-min, payment 24-h holds), `ReleaseExpiredReservations` schedule. |
| **2.4** | Rental Excel bulk import — `excel_imports` + `excel_import_errors`, strict no-partial-commit transactional import. |

The polymorphic base + 3 detail tables decision (per `docs/specs/03_Three_Product_Types.md`) lets booking, payments, and refund logic key off `services.product_type` without joins for type-aware paths.

Depends on: Identity (Phase 1.x — vendors must be approved per type), Geography (Phase 0.1 — no direct dependency in 003 but vendor coverage areas reference cities).

---

## Technical Context

**Language/Version**: PHP 8.3, Laravel 12
**Primary Dependencies**: `spatie/laravel-translatable` (EN/AR JSON columns), `spatie/laravel-medialibrary` (gallery), `spatie/laravel-permission` (per-type vendor permissions), `maatwebsite/excel` (bulk import), `filament/spatie-laravel-translatable-plugin`
**Storage**: MySQL 8 — `services` polymorphic base + 3 detail tables, `service_inventory_reservations` for hold tracking, `excel_imports` + `excel_import_errors` for bulk import audit
**Testing**: Pest — feature tests for create per type, inventory overlap, Excel happy/error paths; unit tests on enum and factories
**Target Platform**: Linux (Docker Compose); MinIO/Spaces for gallery media
**Project Type**: Modular monolith API + Filament admin
**Performance Goals**: Service create p95 under 250ms; inventory overlap check under 50ms; Excel import 100 rows under 10s
**Constraints**:
- Money as integer minor units only (`base_price_minor` BIGINT + `base_price_currency` CHAR(3)); no DECIMAL/FLOAT
- ULID `public_id` on every top-level entity
- Translatable fields stored as JSON columns, not parallel `_en`/`_ar` columns
- ❌ Single Table Inheritance forbidden — polymorphic base + 3 detail tables only
- ❌ Three independent service tables forbidden
- ❌ `if/elseif` chains on `product_type` strings — `match($enum)` only
- ❌ Business logic in Models — relationships, casts, scopes only
- Cart hold TTL = 15 min; payment hold TTL = 24h (locked in 11_DB_Schema.md)
- Excel import = strict no-partial-commit (FR-22)

**Scale/Scope**: Phase 2 — single-vendor pilot dataset; up to ~500 services per vendor; up to ~50 vendors per category; gallery max 11 images per service

---

## Constitution Check

*GATE: Must pass before Phase 0 research. All retro-verified against the shipped code.*

| Rule | Status | Notes |
|---|---|---|
| Modular monolith — `app/Modules/Catalog/` | ✅ PASS | Full module structure: Domain/, Application/, Infrastructure/, Http/, Filament/, Routes/, Database/ |
| Thin controllers (max 3-line action body) | ✅ PASS | `RentalServiceController`, `SaleServiceController`, `DigitalServiceController` delegate to `Create{Type}ServiceAction::execute()` |
| Per-type Actions, not one polymorphic action | ✅ PASS | `CreateRentalServiceAction`, `CreateSaleServiceAction`, `CreateDigitalServiceAction` |
| Per-type Filament resources | ✅ PASS | `RentalServiceResource`, `SaleServiceResource`, `DigitalServiceResource` under "Services" navigation |
| `match($enum)` not `if/elseif` on type strings | ✅ PASS | `HoldServiceInventoryAction` branches via `match(ProductType $type)` |
| No cross-module Eloquent model imports | ✅ PASS | Identity access via `EloquentPaymentsCatalogReader` repository contract; no direct `VendorProfile::find()` in Catalog code |
| Polymorphic services table + 3 detail tables (no STI) | ✅ PASS | `services` (base) + `service_rental_details`, `service_sale_details`, `service_digital_details` (1:1, FK PK) |
| Money as integer minor units | ✅ PASS | `base_price_minor` BIGINT + `base_price_currency` CHAR(3); no DECIMAL/FLOAT |
| ULID `public_id` on all top-level entities | ✅ PASS | `services`, `occasions`, `categories`, `excel_imports` all have `public_id` CHAR(26) UNIQUE |
| Translatable fields = JSON columns | ✅ PASS | `name`, `description`, `short_description` stored as JSON via `spatie/laravel-translatable` |
| Append-only `excel_imports` + `excel_import_errors` | ⚠️ PARTIAL | `excel_import_errors` is append-only ✅; `excel_imports` has `status` column updates (allowed by §15 carve-out for status-only updates) ✅ |
| Soft-deletes only on services | ✅ PASS | `services.deleted_at` exists; detail tables, reservations, imports — no soft deletes |
| Domain events fire after `DB::afterCommit` | ✅ PASS | `RentalServiceCreated`, `SaleServiceCreated`, `DigitalServiceCreated`, `ServicePublished`, `ServiceArchived` all dispatched via `DB::afterCommit()` |
| Idempotency-Key on payment-mutating endpoints | ✅ N/A | Catalog endpoints are not payment-mutating |
| `ApiResponse` envelope on every API response | ✅ PASS | All controller methods return `*ServiceResource` wrapped via the standard envelope |
| Locale conversion at API Resource layer | ✅ PASS | `ServiceBaseResource::resolveLocale()` keys off `Accept-Language` header, never inside Actions |
| Excel import = strict no-partial-commit | ✅ PASS | `ImportRentalServicesFromExcelAction::execute()` wraps the whole batch in `DB::transaction`; any row failure rolls back all rows |
| Cart hold = 15 min, payment hold = 24 h | ✅ PASS | `HoldServiceInventoryAction` uses `HoldType::Cart->ttlMinutes()` (15) and `HoldType::Payment->ttlMinutes()` (1440) |
| Per-type permissions enforced | ✅ PASS | `service.create.rental.own`, `service.create.sale.own`, `service.create.digital.own` checked in Form Requests `authorize()` |

**GATE RESULT: ALL PASS — implementation accepted to `master`.**

---

## Project Structure

### Documentation (this feature)

```text
specs/003-catalog-services/
├── spec.md                ← Feature specification (5 user stories, 15 FRs)
├── plan.md                ← This file (retrospective)
├── tasks.md               ← Retrospective task list, all [x]
└── checklists/            ← Acceptance + review checklists
```

### Source Code (as shipped on master)

```text
app/Modules/Catalog/
├── Domain/
│   ├── Enums/
│   │   ├── HoldType.php                       # cart | payment (TTL minutes)
│   │   ├── ProductType.php                    # rental | sale | digital
│   │   ├── ReservationStatus.php              # held | confirmed | expired | released
│   │   └── ServiceStatus.php                  # draft | pending_review | published | archived
│   ├── Events/
│   │   ├── DigitalServiceCreated.php
│   │   ├── RentalServiceCreated.php
│   │   ├── SaleServiceCreated.php
│   │   ├── ServiceArchived.php
│   │   └── ServicePublished.php
│   ├── Exceptions/
│   │   └── InventoryNotAvailableException.php # thrown by HoldServiceInventoryAction overlap path
│   └── Models/
│       ├── Category.php                       # self-ref parent_id, allowed_product_types JSON
│       ├── CategoryFieldSchema.php            # keyed by (category_id, product_type)
│       ├── ExcelImport.php                    # bulk-import audit record (status updates allowed)
│       ├── ExcelImportError.php               # append-only error log
│       ├── Occasion.php
│       ├── Service.php                        # polymorphic base, product_type discriminator
│       ├── ServiceDigitalDetail.php           # 1:1 service_id PK
│       ├── ServiceInventoryReservation.php    # cart/payment holds
│       ├── ServiceRentalDetail.php            # 1:1 service_id PK
│       ├── ServiceSaleDetail.php              # 1:1 service_id PK
│       └── ServiceTheme.php
├── Application/
│   ├── Actions/
│   │   ├── CreateDigitalServiceAction.php
│   │   ├── CreateRentalServiceAction.php
│   │   ├── CreateSaleServiceAction.php
│   │   ├── HoldServiceInventoryAction.php     # match($enum) per-type hold logic
│   │   ├── ImportRentalServicesFromExcelAction.php  # strict transactional, no partial commits
│   │   └── ReleaseExpiredReservationsAction.php     # called by every-minute schedule
│   ├── DTOs/
│   │   ├── CreateDigitalServiceDTO.php
│   │   ├── CreateRentalServiceDTO.php
│   │   └── CreateSaleServiceDTO.php
│   └── Listeners/
│       └── ArchiveServicesOnTypeRevokedListener.php # listens for VendorTypeRevoked from Identity
├── Infrastructure/
│   ├── Importers/
│   │   └── RentalServicesImport.php           # maatwebsite/excel adapter
│   └── Repositories/
│       ├── EloquentCatalogServiceReader.php   # used by Discovery for Scout indexing
│       └── EloquentPaymentsCatalogReader.php  # used by Payments for service lookups (no model exposure)
├── Http/
│   ├── Controllers/
│   │   ├── CategoryController.php
│   │   ├── OccasionController.php
│   │   └── Vendor/
│   │       ├── DigitalServiceController.php
│   │       ├── RentalServiceController.php
│   │       └── SaleServiceController.php
│   ├── Requests/
│   │   ├── CreateDigitalServiceRequest.php
│   │   ├── CreateRentalServiceRequest.php
│   │   ├── CreateSaleServiceRequest.php
│   │   ├── UpdateDigitalServiceRequest.php
│   │   ├── UpdateRentalServiceRequest.php
│   │   └── UpdateSaleServiceRequest.php
│   └── Resources/
│       ├── DigitalServiceResource.php
│       ├── RentalServiceResource.php
│       ├── SaleServiceResource.php
│       └── ServiceBaseResource.php            # locale resolver shared by all 3
├── Filament/
│   ├── Pages/
│   │   └── ImportRentalServicesPage.php
│   └── Resources/
│       ├── CategoryResource.php (+ Pages/)
│       ├── DigitalServiceResource.php (+ Pages/)
│       ├── OccasionResource.php (+ Pages/)
│       ├── RentalServiceResource.php (+ Pages/)
│       └── SaleServiceResource.php (+ Pages/)
├── Routes/
│   ├── admin.php
│   ├── customer.php
│   └── vendor.php
├── Database/
│   ├── Factories/                             # 9 factories
│   ├── Migrations/                            # 13 migrations (see Schema below)
│   └── Seeders/CatalogSeeder.php
├── Resources/
│   ├── lang/{en,ar}/catalog.php
│   └── views/filament/pages/import-rental-services.blade.php
└── Providers/CatalogServiceProvider.php
```

---

## Schema (as shipped — matches `docs/specs/11_DB_Schema.md`)

Migrations in FK dependency order (run via `php artisan migrate`):

| # | Migration | Tables / Changes | Phase |
|---|---|---|---|
| 1 | `2026_01_01_000020_create_occasions_table` | `occasions` (translatable name, slug, sort_order) | 2.0 |
| 2 | `2026_01_01_000021_create_categories_table` | `categories` (self-ref parent_id, allowed_product_types JSON) | 2.0 |
| 3 | `2026_01_01_000022_create_occasion_category_table` | pivot | 2.0 |
| 4 | `2026_01_01_000023_create_category_field_schemas_table` | per (category × type) field schema | 2.0 |
| 5 | `2026_01_01_000024_create_service_themes_table` | (deferred UI; table exists) | 2.0 |
| 6 | `2026_01_01_000025_create_services_table` | polymorphic base, product_type ENUM discriminator | 2.1 |
| 7 | `2026_01_01_000026_create_service_rental_details_table` | 1:1 service_id PK; requires_electricity, security_deposit_minor, etc. | 2.1 |
| 8 | `2026_01_01_000027_create_service_sale_details_table` | 1:1 service_id PK; is_made_to_order, lead_time_hours, customization_fields JSON, stock_quantity | 2.2 |
| 9 | `2026_01_01_000028_create_service_digital_details_table` | 1:1 service_id PK; delivery_method, expiry_days_after_purchase, is_refundable_after_delivery | 2.3 |
| 10 | `2026_01_01_000029_create_service_inventory_reservations_table` | cart/payment holds, status state machine | 2.3 |
| 11 | `2026_01_01_000030_create_excel_imports_table` | per-vendor import audit | 2.4 |
| 12 | `2026_01_01_000031_create_excel_import_errors_table` | append-only per-row error log | 2.4 |
| 13 | `2026_01_01_000032_add_release_columns_to_service_inventory_reservations_table` | `released_at`, `released_by`, `release_reason` (added during integration with Booking) | 2.3 follow-up |

All migrations:
- `utf8mb4` charset / `utf8mb4_unicode_ci` collation
- ULID `public_id` on top-level entities (`occasions`, `categories`, `services`, `excel_imports`)
- Money columns: BIGINT `_minor` + CHAR(3) `_currency` pair
- Translatable JSON columns for `name`, `description`, `short_description`
- FK with `restrictOnDelete()` by default; `cascadeOnDelete()` only on detail tables and pivot

---

## Per-type Coverage

Every type-aware operation has three parallel implementations:

| Concern | Rental | Sale | Digital |
|---|---|---|---|
| Form Request | `CreateRentalServiceRequest` | `CreateSaleServiceRequest` | `CreateDigitalServiceRequest` |
| Update Form Request | `UpdateRentalServiceRequest` | `UpdateSaleServiceRequest` | `UpdateDigitalServiceRequest` |
| DTO | `CreateRentalServiceDTO` | `CreateSaleServiceDTO` | `CreateDigitalServiceDTO` |
| Action | `CreateRentalServiceAction` | `CreateSaleServiceAction` | `CreateDigitalServiceAction` |
| API Resource | `RentalServiceResource` | `SaleServiceResource` | `DigitalServiceResource` |
| Filament Resource | `RentalServiceResource` (Filament) | `SaleServiceResource` (Filament) | `DigitalServiceResource` (Filament) |
| Domain Event | `RentalServiceCreated` | `SaleServiceCreated` | `DigitalServiceCreated` |
| Inventory hold semantics | overlap check on `(starts_at, ends_at)` | stock_quantity decrement | always-available (no constraint) |
| Detail table | `service_rental_details` | `service_sale_details` | `service_digital_details` |

Cross-type code uses `match($enum)`:
- `HoldServiceInventoryAction::execute()` branches by `match(ProductType $type)`
- `RentalServicesImport` is rental-only by design — `SaleServicesImport` and `DigitalServicesImport` deferred to Phase 6.1

---

## Locale Coverage

Every translatable field is EN+AR required:
- `services.name`, `services.description`, `services.short_description`
- `occasions.name`, `occasions.description`
- `categories.name`, `categories.description`
- `service_themes.name`
- `excel_import_errors.message_en` / `message_ar` (denormalized for fast vendor display)

Locale enforcement points:
1. Form Request validation rejects when either locale is missing (`required_with:name.en` / `required_with:name.ar`)
2. Filament forms render EN/AR tabs via `filament/spatie-laravel-translatable-plugin`
3. API Resources resolve locale from `Accept-Language` header at the Resource layer (never in Actions)
4. Excel import accepts flat columns `name_en` / `name_ar` and maps to JSON

---

## Idempotency

Catalog endpoints are not payment-mutating; no `Idempotency-Key` middleware applied. Excel import uses transactional all-or-nothing semantics instead of idempotency keys.

---

## Domain Events

All events fire after `DB::afterCommit()`:

| Event | Trigger | Listeners |
|---|---|---|
| `RentalServiceCreated` | `CreateRentalServiceAction` | (Discovery) `IndexServiceListener` (Scout) |
| `SaleServiceCreated` | `CreateSaleServiceAction` | (Discovery) `IndexServiceListener` |
| `DigitalServiceCreated` | `CreateDigitalServiceAction` | (Discovery) `IndexServiceListener` |
| `ServicePublished` | admin publish action | (Discovery) `IndexServiceListener` re-index |
| `ServiceArchived` | `ArchiveServicesOnTypeRevokedListener` (reacts to Identity's `VendorTypeRevoked`) | (Discovery) `IndexServiceListener` remove |

Cross-module event flow (no model imports):
- Identity emits `VendorTypeRevoked` → Catalog's `ArchiveServicesOnTypeRevokedListener` archives services for the revoked type
- Catalog emits `*ServiceCreated` / `ServicePublished` / `ServiceArchived` → Discovery indexes/de-indexes via Scout

---

## API Documentation Plan (status: documented)

| Method | Path | Auth | Roles | Purpose |
|---|---|---|---|---|
| `GET`  | `/api/v1/customer/occasions` | guest | — | List active occasions (locale-aware) |
| `GET`  | `/api/v1/customer/categories` | guest | — | List categories filtered by `occasion_id`, `product_type` |
| `POST` | `/api/v1/vendor/services/rental` | sanctum | `service.create.rental.own` | Create rental service |
| `PATCH` | `/api/v1/vendor/services/rental/{public_id}` | sanctum | `service.update.rental.own` | Update rental |
| `POST` | `/api/v1/vendor/services/sale` | sanctum | `service.create.sale.own` | Create sale service |
| `PATCH` | `/api/v1/vendor/services/sale/{public_id}` | sanctum | `service.update.sale.own` | Update sale |
| `POST` | `/api/v1/vendor/services/digital` | sanctum | `service.create.digital.own` | Create digital service |
| `PATCH` | `/api/v1/vendor/services/digital/{public_id}` | sanctum | `service.update.digital.own` | Update digital |

Documentation status (retro):
- ⚠️ `@bodyParam` PHPDoc on Form Requests — partial; Phase 6.0 sweep will fill gaps
- ⚠️ `@response` PHPDoc on API Resources with EN+AR examples — partial; same sweep
- ⚠️ `.specify/memory/api-registry.md` entries — pending Phase 6.0 backfill
- ⚠️ Bruno collection `docs/api/collections/catalog.bru` — pending Phase 6.0 backfill
- ⚠️ Postman collection `docs/api/collections/catalog.postman_collection.json` — pending Phase 6.0 backfill

The retro-flagged ⚠️ items are tracked as Phase 6.0 (Reports + Audit) backfill tasks since the Catalog code shipped before the API-doc enforcement rule was tightened.

---

## Packages Used (all in `docs/specs/10_Package_List.md`)

- `spatie/laravel-translatable` — JSON locale columns
- `spatie/laravel-medialibrary` — gallery (max 11 images per service)
- `spatie/laravel-permission` — per-type service permissions (`service.{action}.{type}.own`)
- `filament/filament` v3 + `filament/spatie-laravel-translatable-plugin` — admin EN/AR tabs
- `bezhansalleh/filament-shield` — permission generation (`shield:generate --all`)
- `maatwebsite/excel` — `RentalServicesImport`
- `brick/money` — `MoneyCast` for `base_price_minor` ↔ `Brick\Money\Money`
- `awcodes/filament-tiptap-editor` — used in CMS only, not Catalog (excluded)

No new packages were installed for this feature.

---

## Architecture Tests

Architecture tests verifying Catalog module rules:
- ✅ `tests/Architecture/AppendOnlyTablesHaveNoSoftDeletesTest` — confirms `excel_import_errors` has no `deleted_at`
- ✅ `tests/Architecture/PaymentsModuleNoCrossImportTest` — symmetric: confirms Catalog code does not import Payments models
- ✅ `tests/Feature/Modules/Catalog/CreateServiceTest` — happy + auth + authz + validation + locale, all 3 types
- ✅ `tests/Feature/Modules/Catalog/InventoryReservationTest` — overlap, expiry, release
- ✅ `tests/Feature/Modules/Catalog/ExcelImportTest` — happy + invalid-row rollback

---

## Cut-list (carried from `docs/specs/09_Phasing_Plan.md`)

Deferred to Phase 1.5 / 6.x:
- Pricing tiers UI (`service_pricing_tiers` migration exists but no admin form) — Phase 6.x
- Availability blocks management — Phase 6.x (services treated as always-available in Phase 2)
- Service themes pivot UI — Phase 6.x
- Sale + digital Excel imports — Phase 6.1
- Excel image folder upload (filename references only in 2.4) — Phase 6.x
- Digital `code_pool_id` (digital code pools) — Phase 1.5
- Excel template download endpoint — Phase 6.1

---

## Implementation Order (as shipped — Day by Day)

### Phase 2.0 — Foundation (Day 1–2)
1. ADR-0004 drafted, accepted
2. Migrations 1–5 (occasions → categories → pivot → field_schemas → service_themes)
3. Models with translatable JSON columns
4. Factories + `CatalogSeeder` (Birthday/Wedding/Engagement + sample categories)
5. Filament: `OccasionResource`, `CategoryResource` (tree view); customer API list endpoints
6. Pest: tree relationships, allowed_product_types filter

### Phase 2.1 — Rental (Day 3–4)
1. `ProductType` enum
2. Migrations 6–7 (services + service_rental_details)
3. `CreateRentalServiceRequest` / DTO / Action
4. `RentalServiceController` + API Resource
5. `RentalServiceResource` (Filament) under "Services" navigation, EN/AR tabs, gallery (Spatie Media Library)
6. Pest: rental create happy + auth + authz + validation + locale

### Phase 2.2 — Sale (Day 5–6)
1. Migration 8 (service_sale_details)
2. Sale Form Request / DTO / Action with conditional `lead_time_hours` validation
3. `SaleServiceController` + API Resource
4. `SaleServiceResource` (Filament)
5. Pest: standard + `is_made_to_order` conditional + `customization_fields` JSON

### Phase 2.3 — Digital + Inventory (Day 7–8)
1. Migrations 9–10 (service_digital_details + service_inventory_reservations)
2. Digital Form Request / DTO / Action
3. `HoldServiceInventoryAction` with `match($enum)` per-type logic
4. `ReleaseExpiredReservationsAction` + `every-minute` schedule registration in `routes/console.php`
5. `DigitalServiceResource` (Filament)
6. Pest: cart 15-min expiry, payment 24h expiry, rental overlap, sale stock decrement, digital always-available
7. Migration 13 (release columns) added during Booking integration follow-up

### Phase 2.4 — Rental Excel Import (Day 9)
1. Migrations 11–12 (excel_imports + excel_import_errors)
2. `RentalServicesImport` (Maatwebsite Excel adapter)
3. `ImportRentalServicesFromExcelAction` (strict transactional)
4. `ImportRentalServicesPage` (Filament) with progress + per-row errors
5. Pest: 100% valid → all imported; 1 invalid → 0 imported with errors logged

---

## Complexity Tracking

| Decision | Why simpler alternative was rejected |
|---|---|
| Polymorphic base + 3 detail tables (vs. STI) | Per-type queries can stay narrow (`service_rental_details` is small); STI would bloat `services` with mostly-NULL columns |
| `match($enum)` vs strategy pattern | Three concrete cases, no plugin extensibility needed in Phase 1; strategy pattern adds indirection without payoff |
| Strict no-partial-commit Excel | FR-22 explicitly requires it; partial-commit would create vendor data confusion |
| `excel_import_errors` denormalized message_en/message_ar | Avoids re-translating at display time; reading rows is the hot path |

---

## Retrospective Notes

- Migration 13 (`add_release_columns_to_service_inventory_reservations_table`) was added during the Phase 3.1 Booking integration when we needed to track release reasons. It logically belongs here but was discovered late.
- `ServiceTheme` model + table exist but have no admin UI yet (deferred per cut-list).
- API documentation backfill (@bodyParam, @response, registry, Bruno, Postman) is the largest hanging thread — tracked as Phase 6.0 work, not re-shipped here.
- Plan + tasks files were not generated alongside spec.md at the time the feature shipped; this retrospective restores the spec-kit precedent so future folders (008+) follow the full pattern.
