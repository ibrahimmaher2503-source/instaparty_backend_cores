> **RETROSPECTIVE** — generated after implementation for spec-kit precedent. Reflects code as shipped on branch `003-catalog-services` (merged to `master` via 631406e). Every task is `[x]` because the implementation exists in `master`. Original ordering reconstructed from git history + file inventory; some inter-story tasks may have been done out of order in practice.

# Tasks: Catalog Service Management (Phases 2.0–2.4)

**Input**: Design documents from `specs/003-catalog-services/`
**Prerequisites**: spec.md, plan.md (this retro), ADR-0004
**Branch**: `003-catalog-services` → merged into `master`

---

## Format: `[ID] [P?] [Story] Description`

- **[P]**: parallel-safe (no shared file with another `[P]` task in the same phase)
- **[Story]**: one of US1, US2, US3, US4, US5, FOUND (foundational), or POLISH

---

## Phase 1: Setup (Module Scaffold)

- [x] **T001** [FOUND] Draft and accept ADR-0004 — Catalog Module
  - File: `docs/adr/0004-catalog-module.md`
  - Source: ADR-0004 §1–6 (decisions on polymorphic base, ProductType enum location, inventory model)

- [x] **T002** [P] [FOUND] Scaffold Catalog module directory structure
  - Files: `app/Modules/Catalog/{Domain,Application,Infrastructure,Http,Filament,Routes,Database,Resources,Providers}/`
  - Source: `.claude/rules/modules.md` (mandatory layer layout)

- [x] **T003** [FOUND] Register `CatalogServiceProvider` in `bootstrap/app.php`
  - File: `app/Modules/Catalog/Providers/CatalogServiceProvider.php`, `bootstrap/app.php`
  - Source: `.claude/rules/modules.md` §ServiceProvider

---

## Phase 2: Foundational — Phase 2.0 (Catalog Foundation)

**Blocks**: All user stories (services need occasions + categories to exist)

### Migrations

- [x] **T010** [P] [FOUND] Migration: create `occasions` table
  - File: `app/Modules/Catalog/Database/Migrations/2026_01_01_000020_create_occasions_table.php`
  - Source: Schema §Catalog, FR-CAT-014

- [x] **T011** [FOUND] Migration: create `categories` table (self-ref `parent_id`, `allowed_product_types` JSON)
  - File: `app/Modules/Catalog/Database/Migrations/2026_01_01_000021_create_categories_table.php`
  - Source: Schema §Catalog
  - Depends on: T010 (FK ordering)

- [x] **T012** [FOUND] Migration: create `occasion_category` pivot
  - File: `app/Modules/Catalog/Database/Migrations/2026_01_01_000022_create_occasion_category_table.php`
  - Source: Schema §Catalog
  - Depends on: T010, T011

- [x] **T013** [P] [FOUND] Migration: create `category_field_schemas` (keyed by `category_id` × `product_type`)
  - File: `app/Modules/Catalog/Database/Migrations/2026_01_01_000023_create_category_field_schemas_table.php`
  - Source: Schema §Catalog, ADR-0004 §6.4

- [x] **T014** [P] [FOUND] Migration: create `service_themes`
  - File: `app/Modules/Catalog/Database/Migrations/2026_01_01_000024_create_service_themes_table.php`
  - Source: Schema §Catalog (UI deferred per cut-list)

### Domain Models

- [x] **T020** [P] [FOUND] Model: `Occasion` (translatable name + description)
  - File: `app/Modules/Catalog/Domain/Models/Occasion.php`
  - Source: ADR-0004 §3, `.claude/rules/modules.md`

- [x] **T021** [P] [FOUND] Model: `Category` (translatable, self-ref, `allowed_product_types` cast)
  - File: `app/Modules/Catalog/Domain/Models/Category.php`
  - Source: Schema §Catalog

- [x] **T022** [P] [FOUND] Model: `CategoryFieldSchema` (keyed by category × product_type)
  - File: `app/Modules/Catalog/Domain/Models/CategoryFieldSchema.php`
  - Source: ADR-0004 §6.4

### Factories + Seeders

- [x] **T030** [P] [FOUND] Factory: `OccasionFactory`
  - File: `app/Modules/Catalog/Database/Factories/OccasionFactory.php`

- [x] **T031** [P] [FOUND] Factory: `CategoryFactory`
  - File: `app/Modules/Catalog/Database/Factories/CategoryFactory.php`

- [x] **T032** [P] [FOUND] Factory: `CategoryFieldSchemaFactory`
  - File: `app/Modules/Catalog/Database/Factories/CategoryFieldSchemaFactory.php`

- [x] **T033** [FOUND] Seeder: `CatalogSeeder` — Birthday / Wedding / Engagement + sample categories per type
  - File: `app/Modules/Catalog/Database/Seeders/CatalogSeeder.php`

### API + Filament

- [x] **T040** [P] [FOUND] API: `OccasionController` + route + Resource (customer locale-aware)
  - Files: `app/Modules/Catalog/Http/Controllers/OccasionController.php`, `app/Modules/Catalog/Routes/customer.php`
  - Source: `GET /api/v1/customer/occasions`

- [x] **T041** [P] [FOUND] API: `CategoryController` + route (filterable by `occasion_id`, `product_type`)
  - File: `app/Modules/Catalog/Http/Controllers/CategoryController.php`
  - Source: `GET /api/v1/customer/categories`

- [x] **T042** [P] [FOUND] Filament: `OccasionResource` with EN/AR tabs
  - File: `app/Modules/Catalog/Filament/Resources/OccasionResource.php` + Pages

- [x] **T043** [P] [FOUND] Filament: `CategoryResource` (tree view, parent_id)
  - File: `app/Modules/Catalog/Filament/Resources/CategoryResource.php` + Pages

- [x] **T044** [FOUND] Run `php artisan shield:generate --all`

---

## Phase 3: User Story 1 — Vendor Creates a Rental Service (Priority: P1) 🎯 MVP

**Independent Test**: `POST /api/v1/vendor/services/rental` with valid payload → 201 with public_id; missing required field → 422.

### Implementation

- [x] **T100** [US1] Enum: `ProductType` (rental | sale | digital)
  - File: `app/Modules/Catalog/Domain/Enums/ProductType.php`
  - Source: ADR-0004 §6.3 (Option A — owned by Catalog)

- [x] **T101** [US1] Enum: `ServiceStatus` (draft | pending_review | published | archived)
  - File: `app/Modules/Catalog/Domain/Enums/ServiceStatus.php`

- [x] **T102** [US1] Migration: create `services` (polymorphic base, `product_type` ENUM, soft-deletes)
  - File: `app/Modules/Catalog/Database/Migrations/2026_01_01_000025_create_services_table.php`
  - Source: Schema §Catalog, FR-CAT-001 to FR-CAT-005

- [x] **T103** [US1] Migration: create `service_rental_details` (1:1 service_id PK, `requires_electricity`, `security_deposit_minor`, etc.)
  - File: `app/Modules/Catalog/Database/Migrations/2026_01_01_000026_create_service_rental_details_table.php`

- [x] **T104** [US1] Model: `Service` (relationships, casts, soft deletes — no business logic)
  - File: `app/Modules/Catalog/Domain/Models/Service.php`

- [x] **T105** [US1] Model: `ServiceRentalDetail` (1:1, FK PK, no auto-increment)
  - File: `app/Modules/Catalog/Domain/Models/ServiceRentalDetail.php`

- [x] **T106** [US1] Factory: `ServiceFactory` + `ServiceRentalDetailFactory`
  - Files: `app/Modules/Catalog/Database/Factories/ServiceFactory.php`, `ServiceRentalDetailFactory.php`

- [x] **T107** [US1] Form Request: `CreateRentalServiceRequest` (per-type permission + EN/AR required)
  - File: `app/Modules/Catalog/Http/Requests/CreateRentalServiceRequest.php`
  - Source: FR-CAT-003, FR-CAT-004

- [x] **T108** [US1] Form Request: `UpdateRentalServiceRequest`
  - File: `app/Modules/Catalog/Http/Requests/UpdateRentalServiceRequest.php`

- [x] **T109** [US1] DTO: `CreateRentalServiceDTO`
  - File: `app/Modules/Catalog/Application/DTOs/CreateRentalServiceDTO.php`

- [x] **T110** [US1] Action: `CreateRentalServiceAction::execute()` (transactional, fires `RentalServiceCreated` after commit)
  - File: `app/Modules/Catalog/Application/Actions/CreateRentalServiceAction.php`
  - Source: `.claude/rules/actions.md` (single execute, DB::afterCommit)

- [x] **T111** [US1] Domain event: `RentalServiceCreated`
  - File: `app/Modules/Catalog/Domain/Events/RentalServiceCreated.php`

- [x] **T112** [US1] Controller: `Vendor/RentalServiceController` (3-line action body)
  - File: `app/Modules/Catalog/Http/Controllers/Vendor/RentalServiceController.php`

- [x] **T113** [US1] Route: `POST/PATCH /api/v1/vendor/services/rental` in `Routes/vendor.php`
  - File: `app/Modules/Catalog/Routes/vendor.php`

- [x] **T114** [US1] API Resource: `RentalServiceResource` extending `ServiceBaseResource`
  - File: `app/Modules/Catalog/Http/Resources/RentalServiceResource.php`

- [x] **T115** [US1] Filament Resource: `RentalServiceResource` (EN/AR tabs, `->money('EGP', divideBy: 100)`, gallery via Media Library, `product_type` badge)
  - Files: `app/Modules/Catalog/Filament/Resources/RentalServiceResource.php` + `Pages/{CreateRentalService,EditRentalService,ListRentalServices}.php`
  - Source: `.claude/rules/filament.md`, `.claude/rules/filament-components.md`

- [x] **T116** [US1] Run `php artisan shield:generate --all` for new resource

### Tests

- [x] **T120** [P] [US1] Pest: rental creation happy path
  - File: `tests/Feature/Modules/Catalog/CreateServiceTest.php` group `rental`

- [x] **T121** [P] [US1] Pest: rental auth (401) + authz (403 for non-rental-approved vendor)
  - File: same

- [x] **T122** [P] [US1] Pest: rental validation (missing `default_rental_duration_hours` → 422)
  - File: same

- [x] **T123** [P] [US1] Pest: rental locale (EN response, AR response)
  - File: same

---

## Phase 4: User Story 2 — Vendor Creates a Sale Service (Priority: P1)

**Independent Test**: `POST /api/v1/vendor/services/sale` with `is_made_to_order=true, lead_time_hours=48` → 201; same payload missing `lead_time_hours` → 422.

### Implementation

- [x] **T200** [US2] Migration: create `service_sale_details` (1:1, `is_made_to_order`, `lead_time_hours`, `customization_fields` JSON, `stock_quantity`)
  - File: `app/Modules/Catalog/Database/Migrations/2026_01_01_000027_create_service_sale_details_table.php`
  - Source: Schema §Catalog, FR-CAT-006

- [x] **T201** [US2] Model: `ServiceSaleDetail` (1:1 FK PK, `customization_fields` JSON cast)
  - File: `app/Modules/Catalog/Domain/Models/ServiceSaleDetail.php`

- [x] **T202** [P] [US2] Factory: `ServiceSaleDetailFactory`
  - File: `app/Modules/Catalog/Database/Factories/ServiceSaleDetailFactory.php`

- [x] **T203** [US2] Form Request: `CreateSaleServiceRequest` with conditional `lead_time_hours required_if is_made_to_order=true`
  - File: `app/Modules/Catalog/Http/Requests/CreateSaleServiceRequest.php`
  - Source: FR-CAT-006

- [x] **T204** [US2] Form Request: `UpdateSaleServiceRequest`
  - File: `app/Modules/Catalog/Http/Requests/UpdateSaleServiceRequest.php`

- [x] **T205** [US2] DTO: `CreateSaleServiceDTO`
  - File: `app/Modules/Catalog/Application/DTOs/CreateSaleServiceDTO.php`

- [x] **T206** [US2] Action: `CreateSaleServiceAction::execute()`
  - File: `app/Modules/Catalog/Application/Actions/CreateSaleServiceAction.php`

- [x] **T207** [US2] Domain event: `SaleServiceCreated`
  - File: `app/Modules/Catalog/Domain/Events/SaleServiceCreated.php`

- [x] **T208** [US2] Controller: `Vendor/SaleServiceController`
  - File: `app/Modules/Catalog/Http/Controllers/Vendor/SaleServiceController.php`

- [x] **T209** [US2] Route: `POST/PATCH /api/v1/vendor/services/sale`
  - File: `app/Modules/Catalog/Routes/vendor.php`

- [x] **T210** [US2] API Resource: `SaleServiceResource`
  - File: `app/Modules/Catalog/Http/Resources/SaleServiceResource.php`

- [x] **T211** [US2] Filament Resource: `SaleServiceResource`
  - Files: `app/Modules/Catalog/Filament/Resources/SaleServiceResource.php` + Pages

- [x] **T212** [US2] Run `php artisan shield:generate --all`

### Tests

- [x] **T220** [P] [US2] Pest: sale creation happy path (`is_made_to_order=true, lead_time_hours=48`)
  - File: `tests/Feature/Modules/Catalog/CreateServiceTest.php` group `sale`

- [x] **T221** [P] [US2] Pest: conditional validation (`is_made_to_order=true` without `lead_time_hours` → 422)
- [x] **T222** [P] [US2] Pest: `customization_fields` JSON storage + retrieval
- [x] **T223** [P] [US2] Pest: sale auth + authz + locale

---

## Phase 5: User Story 3 — Vendor Creates a Digital Service + Inventory Reservation (Priority: P2)

**Independent Test**: `POST /api/v1/vendor/services/digital` → 201; `HoldServiceInventoryAction` for that service returns `held` reservation.

### Implementation

- [x] **T300** [US3] Migration: create `service_digital_details` (1:1, `delivery_method`, `expiry_days_after_purchase`, `is_refundable_after_delivery`)
  - File: `app/Modules/Catalog/Database/Migrations/2026_01_01_000028_create_service_digital_details_table.php`

- [x] **T301** [US3] Migration: create `service_inventory_reservations` (cart/payment holds, status state machine)
  - File: `app/Modules/Catalog/Database/Migrations/2026_01_01_000029_create_service_inventory_reservations_table.php`
  - Source: Schema §Catalog, FR-CAT-007 to FR-CAT-010

- [x] **T302** [US3] Enum: `HoldType` (cart=15min, payment=24h)
  - File: `app/Modules/Catalog/Domain/Enums/HoldType.php`

- [x] **T303** [US3] Enum: `ReservationStatus` (held | confirmed | expired | released)
  - File: `app/Modules/Catalog/Domain/Enums/ReservationStatus.php`

- [x] **T304** [US3] Model: `ServiceDigitalDetail`
  - File: `app/Modules/Catalog/Domain/Models/ServiceDigitalDetail.php`

- [x] **T305** [US3] Model: `ServiceInventoryReservation`
  - File: `app/Modules/Catalog/Domain/Models/ServiceInventoryReservation.php`

- [x] **T306** [US3] Exception: `InventoryNotAvailableException` (thrown by overlap path)
  - File: `app/Modules/Catalog/Domain/Exceptions/InventoryNotAvailableException.php`

- [x] **T307** [P] [US3] Factory: `ServiceDigitalDetailFactory` + `ServiceInventoryReservationFactory`
  - Files: corresponding `Database/Factories/`

- [x] **T308** [US3] Form Request: `CreateDigitalServiceRequest` + `UpdateDigitalServiceRequest`
- [x] **T309** [US3] DTO: `CreateDigitalServiceDTO`
- [x] **T310** [US3] Action: `CreateDigitalServiceAction::execute()`
  - File: `app/Modules/Catalog/Application/Actions/CreateDigitalServiceAction.php`

- [x] **T311** [US3] Action: `HoldServiceInventoryAction` with `match(ProductType $type)` per-type branching
  - File: `app/Modules/Catalog/Application/Actions/HoldServiceInventoryAction.php`
  - Source: `.claude/rules/actions.md` (match($enum), not if/elseif)

- [x] **T312** [US3] Action: `ReleaseExpiredReservationsAction` (called by every-minute schedule)
  - File: `app/Modules/Catalog/Application/Actions/ReleaseExpiredReservationsAction.php`

- [x] **T313** [US3] Schedule registration in `routes/console.php` (every minute)
  - File: `routes/console.php`

- [x] **T314** [US3] Domain event: `DigitalServiceCreated`
  - File: `app/Modules/Catalog/Domain/Events/DigitalServiceCreated.php`

- [x] **T315** [US3] Controller: `Vendor/DigitalServiceController`
- [x] **T316** [US3] Route: `POST/PATCH /api/v1/vendor/services/digital`
- [x] **T317** [US3] API Resource: `DigitalServiceResource`
- [x] **T318** [US3] Filament Resource: `DigitalServiceResource` + Pages
- [x] **T319** [US3] Run `php artisan shield:generate --all`

### Tests

- [x] **T320** [P] [US3] Pest: digital creation happy path
  - File: `tests/Feature/Modules/Catalog/CreateServiceTest.php` group `digital`

- [x] **T321** [P] [US3] Pest: digital auth + authz + validation + locale

- [x] **T322** [US4] Pest: cart hold expires after 15 min (time-travel)
  - File: `tests/Feature/Modules/Catalog/InventoryReservationTest.php`

- [x] **T323** [US4] Pest: payment hold expires after 24 h

- [x] **T324** [US4] Pest: digital "always available" (no overlap check)

---

## Phase 6: User Story 4 — Inventory Guard / Rental Overlap (Priority: P2)

**Independent Test**: Two overlapping holds on the same rental service → second throws `InventoryNotAvailableException`.

### Implementation (most code shared with US3 above)

- [x] **T400** [US4] Index on `service_inventory_reservations(service_id, reserved_starts_at, reserved_ends_at, status)` for fast overlap check
  - File: included in T301 migration

- [x] **T401** [US4] Migration: `add_release_columns_to_service_inventory_reservations` (`released_at`, `released_by`, `release_reason`)
  - File: `app/Modules/Catalog/Database/Migrations/2026_01_01_000032_add_release_columns_to_service_inventory_reservations_table.php`
  - Note: added during Booking integration follow-up

### Tests

- [x] **T420** [P] [US4] Pest: rental overlap detection (same service, overlapping window → 422 / exception)
  - File: `tests/Feature/Modules/Catalog/InventoryReservationTest.php`

- [x] **T421** [P] [US4] Pest: sale stock decrement (5 holds → 6th fails; release one → 6th succeeds)

- [x] **T422** [P] [US4] Pest: cleanup job releases expired holds via `ReleaseExpiredReservations`

---

## Phase 7: User Story 5 — Rental Excel Bulk Import (Priority: P3)

**Independent Test**: 10 valid rows → 10 services; 10 rows with row 7 invalid → 0 services + error report.

### Implementation

- [x] **T500** [US5] Migration: create `excel_imports` (per-vendor audit, status-only updates allowed)
  - File: `app/Modules/Catalog/Database/Migrations/2026_01_01_000030_create_excel_imports_table.php`

- [x] **T501** [US5] Migration: create `excel_import_errors` (append-only, `message_en` + `message_ar` denorm)
  - File: `app/Modules/Catalog/Database/Migrations/2026_01_01_000031_create_excel_import_errors_table.php`

- [x] **T502** [US5] Model: `ExcelImport` + `ExcelImportError`
  - Files: `app/Modules/Catalog/Domain/Models/{ExcelImport,ExcelImportError}.php`

- [x] **T503** [US5] Importer: `RentalServicesImport` (Maatwebsite Excel adapter, flat `name_en`/`name_ar` columns → JSON)
  - File: `app/Modules/Catalog/Infrastructure/Importers/RentalServicesImport.php`

- [x] **T504** [US5] Action: `ImportRentalServicesFromExcelAction::execute()` — strict transactional, no partial commits
  - File: `app/Modules/Catalog/Application/Actions/ImportRentalServicesFromExcelAction.php`
  - Source: FR-CAT-012, FR-CAT-013

- [x] **T505** [US5] Filament Page: `ImportRentalServicesPage` (upload form + per-row errors in vendor's locale)
  - Files: `app/Modules/Catalog/Filament/Pages/ImportRentalServicesPage.php`, `Resources/views/filament/pages/import-rental-services.blade.php`

### Tests

- [x] **T520** [P] [US5] Pest: 100% valid → all imported
  - File: `tests/Feature/Modules/Catalog/ExcelImportTest.php`

- [x] **T521** [P] [US5] Pest: 1 invalid row → 0 imported, error logged on correct row in vendor's locale

- [x] **T522** [P] [US5] Pest: import audit row created with status `succeeded` / `failed`

---

## Phase 8: Polish & Cross-Cutting Concerns

- [x] **T800** [POLISH] Listener: `ArchiveServicesOnTypeRevokedListener` (reacts to `VendorTypeRevoked` from Identity)
  - File: `app/Modules/Catalog/Application/Listeners/ArchiveServicesOnTypeRevokedListener.php`
  - Source: cross-module event flow (no model imports)

- [x] **T801** [POLISH] Domain event: `ServicePublished` (admin publish action)
- [x] **T802** [POLISH] Domain event: `ServiceArchived` (auto-archive on type revocation)

- [x] **T803** [POLISH] Repository: `EloquentCatalogServiceReader` (used by Discovery for Scout indexing)
  - File: `app/Modules/Catalog/Infrastructure/Repositories/EloquentCatalogServiceReader.php`
  - Source: ADR-0004 §7 (cross-module read contract)

- [x] **T804** [POLISH] Repository: `EloquentPaymentsCatalogReader` (used by Payments for service lookups, no model exposure)
  - File: `app/Modules/Catalog/Infrastructure/Repositories/EloquentPaymentsCatalogReader.php`

- [x] **T805** [POLISH] Locale strings: `lang/{en,ar}/catalog.php`
  - Files: `app/Modules/Catalog/Resources/lang/en/catalog.php`, `app/Modules/Catalog/Resources/lang/ar/catalog.php`

- [x] **T806** [POLISH] Architecture test: append-only tables have no soft-deletes (covers `excel_import_errors`)
  - File: `tests/Architecture/AppendOnlyTablesHaveNoSoftDeletesTest.php`

- [x] **T807** [POLISH] Architecture test: Catalog does not import Payments models (and vice versa)
  - File: `tests/Architecture/PaymentsModuleNoCrossImportTest.php`

### Phase 6.0 backfill (deferred, not part of this folder's scope)
The following are **not done** — tracked as Phase 6.0 (Reports + Audit) work:
- [ ] **T900** [POLISH] `@bodyParam` PHPDoc on every Catalog Form Request field
- [ ] **T901** [POLISH] `@response` PHPDoc with realistic EN+AR examples on every Catalog API Resource
- [ ] **T902** [POLISH] `.specify/memory/api-registry.md` entries for all 8 Catalog endpoints
- [ ] **T903** [POLISH] Bruno collection `docs/api/collections/catalog.bru`
- [ ] **T904** [POLISH] Postman collection `docs/api/collections/catalog.postman_collection.json`
- [ ] **T905** [POLISH] Run `php artisan scribe:generate` after T900–T904

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)** — no dependencies
- **Phase 2 (Foundational)** — depends on Phase 1
- **Phase 3 (US1 Rental)** — depends on Phase 2 + Identity Phase 1.1 (per-type approval)
- **Phase 4 (US2 Sale)** — depends on Phase 3 (services table exists)
- **Phase 5 (US3 Digital + Inventory)** — depends on Phase 3
- **Phase 6 (US4 Inventory Guard)** — depends on Phase 5 (reservation table)
- **Phase 7 (US5 Excel Import)** — depends on Phase 3 (rental table exists)
- **Phase 8 (Polish)** — runs concurrently with Phases 3–7

### Within Each Phase

Migrations → Models → Factories → Form Requests → DTOs → Actions → Controllers → Routes → API Resources → Filament Resources → Tests.

### Parallel Opportunities

`[P]` tasks within a phase can run in parallel:
- All factories in Phase 2 are `[P]`
- API Resources + Filament Resources in Phase 3/4/5 are `[P]` once their respective Action exists
- All Pest test tasks (T120–T123, T220–T223, T320–T324, T420–T422, T520–T522) are `[P]` within their phase

---

## Implementation Strategy

### MVP First (Rental only — get one type shipping)
Deliver T001–T044 + T100–T123. This proves the polymorphic-base pattern with one detail table, one Action, one API endpoint, one Filament resource, and a passing Pest suite.

### Incremental Delivery
Once Rental ships, Sale (US2) and Digital + Inventory (US3) follow the same template — copy/adapt the rental classes, swap detail-table fields, add per-type validation. Inventory Guard (US4) is mostly tests since the action exists in US3.

Excel Import (US5) is the only meaningful net-new code after the three types are shipped.

### Cut-list contingency
If Phase 2.4 slips, defer Excel import to Phase 6.1 (already planned). All three product types are non-negotiable for downstream Booking work.

---

## Summary

| Metric | Count |
|---|---|
| Phases | 8 (3 setup/foundational + 5 user stories) |
| Tasks | ~80 (`[x]`) + 6 deferred (`[ ]` — Phase 6.0 backfill) |
| Migrations | 13 |
| Models | 11 |
| Actions | 6 |
| Form Requests | 6 (3 create + 3 update) |
| API endpoints | 8 |
| Filament Resources | 5 (Occasion, Category, RentalService, SaleService, DigitalService) |
| Filament Pages (custom) | 1 (`ImportRentalServicesPage`) |
| Domain events | 5 |
| Pest test files | 3 |

All tasks shipped to `master` via `631406e Merge branch '002-identity-vendor-onboarding'` lineage; the catalog work itself is on commits prior to the merge.

---

## ⏳ Pending Manual Steps (Phase 6.0 backfill — not part of this folder)

API documentation enforcement landed AFTER folder 003 shipped. To bring 003 into compliance with the current standard, Phase 6.0 will:
1. Add `@bodyParam` to all Catalog Form Requests
2. Add `@response` (with EN+AR examples) to all Catalog API Resources
3. Backfill `.specify/memory/api-registry.md` Catalog rows
4. Create `docs/api/collections/catalog.bru` (Bruno) and `docs/api/collections/catalog.postman_collection.json`
5. Run `php artisan scribe:generate`

These items are tracked as T900–T905 above (unchecked); they are not blockers for any downstream phase.
