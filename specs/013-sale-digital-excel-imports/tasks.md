# Tasks: Sale + Digital Excel Imports

**Feature**: Phase 6.1 — Sale + Digital Excel Imports (1 day, Week 7)
**Spec**: [spec.md](./spec.md) | **Plan**: [plan.md](./plan.md)
**PRD**: FR-22 | **Tables**: `excel_imports`, `excel_import_errors` (no migrations)

**Organization**: Tasks are grouped by user story to enable independent implementation and testing.

## Format: `[ID] [P?] [Story?] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: Which user story this task belongs to (US1–US4)

---

## Phase 1: Setup

**Purpose**: Confirm prerequisites before writing any code.

- [x] T001 Verify `excel_imports.product_type` ENUM in the Phase 2.4 migration includes values `'sale'` and `'digital'` — read `app/Modules/Catalog/Database/Migrations/` to confirm no schema work is needed

**Checkpoint**: Prerequisite confirmed — no migrations required, proceed to Phase 2.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: The shared `maatwebsite/excel` importer classes and the two fat Action classes that both admin (US1/US2) and vendor API (US3/US4) flows delegate to. No user story work can begin until this phase is complete.

**⚠️ CRITICAL**: US1–US4 all depend on `ImportSaleServicesFromExcelAction` and `ImportDigitalServicesFromExcelAction` being complete.

### Importer classes (thin — write first)

- [x] T002 [P] Create `app/Modules/Catalog/Infrastructure/Importers/SaleServicesImport.php` — verbatim copy of `RentalServicesImport`: implements `ToCollection + WithHeadingRow`, stores rows in private `Collection $rows`, exposes `getRows(): Collection`
- [x] T003 [P] Create `app/Modules/Catalog/Infrastructure/Importers/DigitalServicesImport.php` — same shape as `SaleServicesImport` with class name `DigitalServicesImport`

### Action-level tests (write before implementing actions — TDD)

- [x] T004 [P] Create `tests/Feature/Modules/Catalog/SaleExcelImportTest.php` — write **failing** tests first: (1) happy path: valid 2-row sale file → `excel_imports.status=completed`, 2 services + 2 `service_sale_details` rows; (2) rollback: one invalid row → `status=failed`, 0 services, `excel_import_errors` populated; (3) conditional: `is_made_to_order=1` with blank `lead_time_hours` → error row logged; (4) bilingual: error `message` JSON has both `en` and `ar` keys. Groups: `catalog`, `import`, `sale`
- [x] T005 [P] Create `tests/Feature/Modules/Catalog/DigitalExcelImportTest.php` — write **failing** tests first: (1) happy path: valid digital file → `status=completed`, services + `service_digital_details`; (2) rollback: one invalid row → 0 services; (3) conditional: `has_expiry=1` with blank `expiry_days_after_purchase` → error row; (4) invalid `delivery_method` value → error row; (5) bilingual error shape. Groups: `catalog`, `import`, `digital`

### Import Actions (implement to make T004/T005 pass)

- [x] T006 Create `app/Modules/Catalog/Application/Actions/ImportSaleServicesFromExcelAction.php` — constructor-inject `CreateSaleServiceAction`; `execute(UploadedFile $file, int $vendorProfileId, string $locale = 'en'): ExcelImport`; four phases: (1) check vendor approved for `ProductType::Sale` or throw 403; (2) store temp file, create `excel_imports` record with `status=pending`, `product_type=sale`; (3) run `SaleServicesImport` via `Excel::toCollection()`, validate ALL rows using `Validator::make()` + second-pass conditional check for `is_made_to_order → lead_time_hours`, collect bilingual `excel_import_errors`; (4) if any errors → rollback, mark `status=failed`; else → `DB::transaction` to call `CreateSaleServiceAction` per row + mark `status=completed`; fire events via `DB::afterCommit`. Validation rules from `data-model.md` §Sale rows.
- [x] T007 Create `app/Modules/Catalog/Application/Actions/ImportDigitalServicesFromExcelAction.php` — identical pattern to T006; constructor-inject `CreateDigitalServiceAction`; approval check for `ProductType::Digital`; second-pass conditional: `has_expiry=1 → expiry_days_after_purchase required`; validation rules from `data-model.md` §Digital rows; DTO fields per `CreateDigitalServiceDTO`

**Checkpoint**: Phase 2 complete — run `./vendor/bin/pest --group=import` and confirm T004/T005 tests pass on T006/T007.

---

## Phase 3: User Story 1 — Admin Imports Sale Services (Priority: P1) 🎯 MVP

**Goal**: Admin opens an "Import Sale Services" modal on `SaleServiceResource`, selects a vendor, uploads `sales.xlsx`, and either all services are created or the import fails with full rollback and a danger notification.

**Independent Test**: Open Filament as admin → navigate to Sale Services → trigger "Import Sale Services" header action → upload a valid 3-row `sales.xlsx` with a vendor selected → verify 3 services appear in the list and `excel_imports.status=completed`.

### Implementation for User Story 1

- [x] T008 [US1] Add `importSaleServices` header `Action` to the `table()` method of `app/Modules/Catalog/Filament/Resources/SaleServiceResource.php` — form: `Select::make('vendor_profile_id')` (searchable, lists approved `VendorProfile` records by `business_name->en`, required) + `FileUpload::make('file')` (disk `local`, directory `excel-imports-temp`, MIME types `xlsx/xls`, required); action closure: resolve `UploadedFile` from stored path, call `ImportSaleServicesFromExcelAction::execute()`, send `Notification::make()->success()` on `completed` or `->danger()` on `failed`; `->icon('heroicon-o-arrow-up-tray')`, `->modalHeading(...)`, `->requiresConfirmation(false)`

**Checkpoint**: US1 complete — admin can import sale services via Filament; success and failure notifications display correctly.

---

## Phase 4: User Story 2 — Admin Imports Digital Services (Priority: P1)

**Goal**: Identical to US1 but for digital services. Admin selects vendor, uploads `digital.xlsx`, receives success or failure notification.

**Independent Test**: Admin triggers "Import Digital Services" on `DigitalServiceResource`, uploads valid `digital.xlsx` → `service_digital_details` rows created.

### Implementation for User Story 2

- [x] T009 [US2] Add `importDigitalServices` header `Action` to the `table()` method of `app/Modules/Catalog/Filament/Resources/DigitalServiceResource.php` — identical form and notification pattern as T008, delegating to `ImportDigitalServicesFromExcelAction::execute()`; action name `importDigitalServices`, label `__('catalog.import_digital_services')`

**Checkpoint**: US1 + US2 complete — both admin import actions available in Filament.

---

## Phase 5: User Story 3 — Vendor Uploads sales.xlsx via API (Priority: P2)

**Goal**: Authenticated vendor POSTs `sales.xlsx` to `/api/v1/vendor/services/sale/import` with their own `store_id`; system infers `vendor_profile_id` from auth and returns a JSON envelope.

**Independent Test**: Authenticated vendor sends `POST /api/v1/vendor/services/sale/import` with valid file and matching `store_id` → `200 {"data":{"status":"completed",...}}`.

### Tests for User Story 3 (write before controller — TDD)

- [x] T010 [P] [US3] Create `tests/Feature/Modules/Catalog/SaleImportApiTest.php` — write **failing** tests: (1) valid file + own store_id → 200 `data.status=completed`; (2) valid file + other vendor's store_id → 403 `store_not_owned`; (3) non-xlsx file → 422 validation error on `file`; (4) missing `store_id` → 422; (5) unauthenticated → 401; (6) invalid row in file → 422 `data.status=failed` with errors array; (7) submitted `vendor_id` body param is ignored — services created under auth vendor. Groups: `catalog`, `api`, `sale`

### Implementation for User Story 3

- [x] T011 [P] [US3] Create `app/Modules/Catalog/Http/Requests/ImportSaleServicesRequest.php` — `rules()`: `store_id` required string size:26; `file` required file mimes:xlsx,xls max:10240. Add `@bodyParam` PHPDoc for Scribe compatibility. Add `vendorProfile(): VendorProfile` method: resolve `$this->user()->vendorProfile()->firstOrFail()`, abort 403 with `store_not_owned` JSON if `public_id !== store_id`
- [x] T012 [US3] Create `app/Modules/Catalog/Http/Controllers/Vendor/ImportSaleServicesController.php` — `store(ImportSaleServicesRequest $request, ImportSaleServicesFromExcelAction $action): JsonResponse`; body ≤3 lines: get vendor via `$request->vendorProfile()`, call `$action->execute(...)`, return `ApiResponse::success(...)` on completed or `ApiResponse::error(...)` 422 on failed
- [x] T013 [US3] Add sale import route to `app/Modules/Catalog/Routes/vendor.php` — `Route::post('services/sale/import', [ImportSaleServicesController::class, 'store'])->name('vendor.services.sale.import')` inside existing `auth:sanctum` middleware group

**Checkpoint**: US3 complete — run `./vendor/bin/pest --group=api,sale` and confirm T010 tests pass.

---

## Phase 6: User Story 4 — Vendor Uploads digital.xlsx via API (Priority: P2)

**Goal**: Identical flow to US3 but for digital services via `/api/v1/vendor/services/digital/import`.

**Independent Test**: Authenticated vendor sends `POST /api/v1/vendor/services/digital/import` with valid digital file → `200 {"data":{"status":"completed",...}}`.

### Tests for User Story 4 (write before controller — TDD)

- [x] T014 [P] [US4] Create `tests/Feature/Modules/Catalog/DigitalImportApiTest.php` — same 7-scenario set as T010 but for digital endpoint; add digital-specific case: invalid `delivery_method` value → 422 errors array. Groups: `catalog`, `api`, `digital`

### Implementation for User Story 4

- [x] T015 [P] [US4] Create `app/Modules/Catalog/Http/Requests/ImportDigitalServicesRequest.php` — identical shape to T011 but for digital; `@bodyParam` PHPDoc; same `vendorProfile()` store-ownership check
- [x] T016 [US4] Create `app/Modules/Catalog/Http/Controllers/Vendor/ImportDigitalServicesController.php` — identical 3-line body as T012, delegating to `ImportDigitalServicesFromExcelAction`
- [x] T017 [US4] Add digital import route to `app/Modules/Catalog/Routes/vendor.php` — `Route::post('services/digital/import', [ImportDigitalServicesController::class, 'store'])->name('vendor.services.digital.import')` inside the `auth:sanctum` group (alongside the T013 entry)

**Checkpoint**: All 4 user stories complete — run full test suite `./vendor/bin/pest --group=catalog` and confirm all pass.

---

## Phase 7: Polish & Cross-Cutting Concerns

- [x] T018 [P] Add `@response` PHPDoc with realistic EN+AR example JSON to `ImportSaleServicesController::store()` and `ImportDigitalServicesController::store()` (Scribe-compatible format, covering 200/422/403 cases)
- [x] T019 [P] Append two entries to `.specify/memory/api-registry.md` for `POST /api/v1/vendor/services/sale/import` and `POST /api/v1/vendor/services/digital/import` (auth, request fields, response shape, FR reference FR-22)
- [x] T020 Run `php artisan shield:generate --all` to regenerate Filament Shield permissions after modifying `SaleServiceResource` and `DigitalServiceResource`

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately
- **Foundational (Phase 2)**: Depends on Phase 1 confirmation — **BLOCKS all user stories**
- **US1 (Phase 3)** and **US2 (Phase 4)**: Both depend on Phase 2 (T006 + T007); US1 and US2 are independent of each other — can run in parallel
- **US3 (Phase 5)** and **US4 (Phase 6)**: Both depend on Phase 2 (T006 + T007); US3 and US4 are independent of each other — can run in parallel; US3/US4 do not depend on US1/US2
- **Polish (Phase 7)**: Depends on all four user stories complete

### Within-Phase Dependencies

| Task | Depends On |
|---|---|
| T006 | T002, T004 (tests written first) |
| T007 | T003, T005 (tests written first) |
| T008 | T006 (action must exist) |
| T009 | T007 (action must exist) |
| T012 | T011, T010 (tests + request written first) |
| T013 | T012 |
| T016 | T015, T014 (tests + request written first) |
| T017 | T016 |
| T018 | T012, T016 |
| T019 | T013, T017 |
| T020 | T008, T009 |

### Parallel Opportunities

- **T002 + T003**: both are thin importer classes in different files — parallel
- **T004 + T005**: action-level test files — parallel
- **T006 + T007**: action implementations — parallel (after T002/T003)
- **T008 + T009**: different Resource files — parallel (after T006/T007)
- **T010 + T011**: API test + Form Request for sale — parallel within US3
- **T014 + T015**: API test + Form Request for digital — parallel within US4
- **US3 (T010–T013) + US4 (T014–T017)**: entire stories run in parallel

---

## Parallel Example: Phase 2 Foundational

```
# Step 1 — parallel (no dependencies):
Task T002: Create SaleServicesImport.php
Task T003: Create DigitalServicesImport.php

# Step 2 — parallel (after step 1, write failing tests first):
Task T004: Create SaleExcelImportTest.php (failing)
Task T005: Create DigitalExcelImportTest.php (failing)

# Step 3 — parallel (make tests pass):
Task T006: Create ImportSaleServicesFromExcelAction.php
Task T007: Create ImportDigitalServicesFromExcelAction.php
```

## Parallel Example: Phase 5 + Phase 6 (both P2 stories)

```
# US3 and US4 fully parallel:
Task T010: SaleImportApiTest.php (failing)      |   Task T014: DigitalImportApiTest.php (failing)
Task T011: ImportSaleServicesRequest.php        |   Task T015: ImportDigitalServicesRequest.php
Task T012: ImportSaleServicesController.php     |   Task T016: ImportDigitalServicesController.php
Task T013: Add sale route to vendor.php         |   Task T017: Add digital route to vendor.php
```

---

## Implementation Strategy

### MVP First (Admin UI — US1 + US2)

1. Complete Phase 1 (T001)
2. Complete Phase 2 (T002–T007) — foundational actions + passing tests
3. Complete Phase 3 (T008) — admin sale import
4. Complete Phase 4 (T009) — admin digital import
5. **STOP and VALIDATE**: Admin can import both types in Filament
6. Continue to vendor API if time allows

### Full Delivery

1. Phase 1 + 2 → foundation ready
2. Phase 3 + 4 (parallel) → admin UI complete
3. Phase 5 + 6 (parallel) → vendor API complete
4. Phase 7 → polish, docs, permissions

---

## Notes

- All 20 tasks touch only the Catalog module + test files — no other modules modified
- Filament admin layer may access `VendorProfile` (Identity module) per the Phase 2.4 cross-module precedent; no Contract needed for the Filament layer only
- Run `./vendor/bin/pest --group=import` after Phase 2 to validate foundational work before any US starts
- Run `./vendor/bin/pest --group=catalog` at end of Phase 6 for full feature validation
- `store_id` in the vendor API maps to `vendor_profile.public_id` (ULID) — no `stores` table exists in the Phase 1 schema
