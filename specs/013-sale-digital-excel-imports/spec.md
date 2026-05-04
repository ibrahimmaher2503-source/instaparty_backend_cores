---
## REQUIRED CONTEXT (load before executing this command)

Before generating any artifact, you MUST silently read these files in order:
1. .specify/memory/constitution.md
2. .specify/memory/project-index.md
3. .specify/memory/api-registry.md
4. docs/specs/01_PRD.md
5. docs/specs/09_Phasing_Plan.md
6. docs/specs/11_DB_Schema.md
7. docs/specs/10_Package_List.md

CONSTRAINTS (non-negotiable):
- Every generated artifact must cite specific FR numbers from 01_PRD.md
- Every generated artifact must cite specific table names from 11_DB_Schema.md
- Every generated artifact must align to a Phase ID from 09_Phasing_Plan.md
- Never suggest a package not in 10_Package_List.md
- Never suggest a Phase 2 feature
- Never contradict docs/specs/02_Tech_Decisions.md locked stack

API DOCUMENTATION CONSTRAINT:
- Every API endpoint generated must include:
  - @bodyParam PHPDoc on every Form Request field (Scribe-compatible)
  - @response PHPDoc with realistic EN+AR example data on every Resource
  - Entry added to .specify/memory/api-registry.md
  - Bruno/Postman collection entry in docs/api/collections/
---

# Feature Specification: Sale + Digital Excel Imports

**Feature Branch**: `013-sale-digital-excel-imports`
**Phase ID**: Phase 6.1 — Sale + Digital Excel Imports (1 day, Week 7)
**PRD Coverage**: FR-22
**Tables Touched**: `excel_imports`, `excel_import_errors` (existing from Phase 2.4)
**Created**: 2026-05-03
**Status**: Draft

---

## User Scenarios & Testing

### User Story 1 — Admin Imports Sale Services for a Vendor (Priority: P1)

An admin needs to bulk-load a vendor's sale service catalogue. The admin opens the "Import Sale Services" action in Filament, selects the target vendor from a searchable dropdown, uploads `sales.xlsx`, and submits. If every row is valid all services are created atomically. If any row fails the entire import is rolled back and the admin sees per-row errors in both EN and AR.

**Why this priority**: Unlocks vendor catalogue population for sale-type vendors; the rental import pattern (Phase 2.4) must be parity-extended to sale. Admins need this to onboard vendors who can't use the API.

**Independent Test**: A spec-configured admin user can open the sale import action in Filament, provide valid vendor + file, and verify `services` count increases by the number of rows.

**Acceptance Scenarios**:

1. **Given** an authenticated admin with `service.moderate` permission, **When** the admin opens the "Import Sale Services" header action on `SaleServiceResource`, selects a valid vendor, uploads a 5-row valid `sales.xlsx`, and clicks Import, **Then** 5 sale services are created, 5 `service_sale_details` rows exist, the `excel_imports` record has `status=completed` and `imported_rows=5`, and a success notification appears.

2. **Given** an authenticated admin, **When** the import is submitted without selecting a vendor, **Then** a validation error is shown and no import record is created.

3. **Given** an authenticated admin, **When** the admin uploads a `sales.xlsx` where row 3 has a missing required `name_en` field, **Then** no services are created, the `excel_imports` record has `status=failed`, `excel_import_errors` contains one row for row 3, and both `message.en` and `message.ar` are non-empty.

4. **Given** an authenticated admin, **When** the admin uploads a non-Excel file (e.g., `.pdf`), **Then** the form rejects the file before submission and no import record is created.

---

### User Story 2 — Admin Imports Digital Services for a Vendor (Priority: P1)

Same flow as Story 1 but for `digital.xlsx`. The admin selects vendor, uploads the digital template, and either all digital services are created atomically or the import fails with full rollback and bilingual per-row errors.

**Why this priority**: Parity with rental and sale imports; digital is one of the three mandatory product types.

**Independent Test**: Admin opens "Import Digital Services" action, uploads a valid `digital.xlsx`, verifies `service_digital_details` rows created.

**Acceptance Scenarios**:

1. **Given** an authenticated admin, **When** the admin selects a valid vendor and uploads a 3-row valid `digital.xlsx`, **Then** 3 digital services are created with `service_digital_details`, `excel_imports.status=completed`, success notification shown.

2. **Given** an authenticated admin, **When** `digital.xlsx` has a row where `delivery_method` is missing, **Then** 0 services created, `excel_imports.status=failed`, per-row error logged with EN+AR message.

---

### User Story 3 — Vendor Uploads sales.xlsx via API (Priority: P2)

A vendor sends a multipart POST request to the sale import endpoint. The system infers `vendor_profile_id` from the authenticated user — submitted `vendor_id` fields are silently ignored. The response is a JSON envelope with import outcome and any per-row errors.

**Why this priority**: Supports mobile/web clients that interact programmatically rather than through Filament.

**Independent Test**: Authenticated vendor POSTs to `/api/v1/vendor/services/sale/import` with a valid file and receives a 200 response with `data.status=completed`.

**Acceptance Scenarios**:

1. **Given** an authenticated vendor approved for `sale`, **When** a valid `sales.xlsx` is POSTed with a valid `store_id` (own vendor_profile public_id), **Then** response is `200` with `data.status=completed` and `data.imported_rows` matching row count.

2. **Given** an authenticated vendor, **When** the request includes a `vendor_id` field pointing to another vendor, **Then** the field is ignored; the authenticated vendor's `vendor_profile_id` is used.

3. **Given** an authenticated vendor, **When** a `store_id` belonging to a different vendor is submitted, **Then** response is `403` with `errors[0].code=store_not_owned`.

4. **Given** an authenticated vendor, **When** a `.pdf` is uploaded instead of `.xlsx`, **Then** response is `422` with `errors[0].code=invalid_file_type`.

5. **Given** an authenticated vendor, **When** `sales.xlsx` has one row with `base_price_minor` missing, **Then** response is `422` with `data.status=failed` and `data.errors` array containing the row number, field, and EN+AR messages; no services created.

---

### User Story 4 — Vendor Uploads digital.xlsx via API (Priority: P2)

Identical flow to Story 3 but for digital services.

**Why this priority**: Parity with Story 3; digital type must be fully covered.

**Independent Test**: Authenticated vendor POSTs to `/api/v1/vendor/services/digital/import` with a valid file and receives `data.status=completed`.

**Acceptance Scenarios**:

1. **Given** an authenticated vendor approved for `digital`, **When** a valid `digital.xlsx` is POSTed, **Then** digital services and `service_digital_details` rows are created; response `200`.

2. **Given** `digital.xlsx` contains a row with invalid `delivery_method`, **Then** 0 services created, response `422` with per-row errors in EN+AR.

---

### Edge Cases

- What happens when the Excel file has zero data rows (header only)? → Import fails with `status=failed` and an error indicating no rows found.
- What happens when the file is corrupt / unreadable by Maatwebsite Excel? → Caught exception returns `422` with a generic parse-failure message (EN+AR).
- What happens if the vendor is approved for `sale` but not `digital`? → The sale import proceeds; the digital import endpoint returns `403` with `errors[0].code=vendor_type_not_approved`.
- What if `store_id` is omitted from the vendor API request? → `422` validation error (required field).
- What if two concurrent imports are submitted for the same vendor? → Each creates its own `excel_imports` record; no race condition since records are inserted before transaction begins. Both may succeed independently.
- Can admin import for a suspended vendor? → Import is allowed at the data layer; admin owns the decision. Vendor type approval check still applies.

---

## Requirements

### Functional Requirements

**Admin UI**

- **FR-22-A**: Admin MUST be able to trigger a sale-service bulk import from the `SaleServiceResource` list page via a header action that opens a modal form.
- **FR-22-B**: Admin MUST be able to trigger a digital-service bulk import from the `DigitalServiceResource` list page via a header action that opens a modal form.
- **FR-22-C**: Each admin import modal MUST include a searchable vendor selector (`vendor_profile_id`), an Excel file upload field, and a submit button. All three fields are required.
- **FR-22-D**: Admin import MUST NOT infer the target vendor from the authenticated admin user. `vendor_profile_id` MUST be supplied explicitly.
- **FR-22-E**: Admin import action MUST call `ImportSaleServicesFromExcelAction::execute()` or `ImportDigitalServicesFromExcelAction::execute()` respectively — no business logic in the Filament closure.
- **FR-22-F**: On success, admin UI MUST show a Filament success notification including the count of imported services.
- **FR-22-G**: On failure, admin UI MUST show a Filament danger notification with the error summary; per-row errors MUST be available on the returned `ExcelImport` record.
- **FR-22-H**: Admin import modal MUST accept only `.xlsx` / `.xls` files (Excel MIME types); other file types MUST be rejected client-side.

**Vendor API**

- **FR-22-I**: A `POST /api/v1/vendor/services/sale/import` endpoint MUST accept `store_id` (vendor_profile public_id) and `file` (Excel upload).
- **FR-22-J**: A `POST /api/v1/vendor/services/digital/import` endpoint MUST accept `store_id` and `file`.
- **FR-22-K**: Both vendor endpoints MUST infer `vendor_profile_id` from `auth()->user()->vendorProfile`; any submitted `vendor_id` body parameter MUST be ignored.
- **FR-22-L**: The `store_id` MUST match the authenticated vendor's `vendor_profile.public_id`; mismatches MUST return `403`.
- **FR-22-M**: Non-Excel uploads MUST return `422` with `errors[0].code=invalid_file_type`.
- **FR-22-N**: Vendor must be approved for the matching product type (`sale` or `digital`); unapproved requests MUST return `403` with `errors[0].code=vendor_type_not_approved`.
- **FR-22-O**: On success, API MUST return `200` with `ApiResponse` envelope containing `data.status=completed`, `data.imported_rows`, `data.total_rows`.
- **FR-22-P**: On import row validation failure, API MUST return `422` with `data.status=failed` and `data.errors` array (row, field, message.en, message.ar).

**Shared Behaviour**

- **FR-22-Q**: Both sale and digital import flows MUST follow the Phase 2.4 pattern: load all rows → validate all rows → atomic commit or full rollback.
- **FR-22-R**: Sale import validation MUST enforce per-type rules: `name_en`, `name_ar`, `short_description_en`, `short_description_ar`, `base_price_minor` (integer ≥0), `is_perishable` (bool), `is_made_to_order` (bool), `lead_time_hours` (required when `is_made_to_order=true`).
- **FR-22-S**: Digital import validation MUST enforce per-type rules: `name_en`, `name_ar`, `short_description_en`, `short_description_ar`, `base_price_minor` (integer ≥0), `delivery_method` (enum), `is_refundable_after_delivery` (bool).
- **FR-22-T**: Error messages in `excel_import_errors.message` MUST be bilingual JSON `{"en":"...","ar":"..."}`.
- **FR-22-U**: `excel_imports` record MUST be created at import start and updated to `completed` or `failed` within the same transaction as the service rows.

### Key Entities

- **`excel_imports`** (`Imports` module): tracks each import attempt — `vendor_profile_id`, `product_type` ENUM('rental','sale','digital'), `status` ENUM('pending','completed','failed'), `total_rows`, `imported_rows`, `error_rows`, `original_filename`, `stored_path`.
- **`excel_import_errors`** (`Imports` module): per-row errors — `excel_import_id`, `row_number`, `field`, `message` JSON `{en,ar}`.
- **`services`** + **`service_sale_details`** / **`service_digital_details`** (`Catalog` module): created atomically on successful import.
- **`vendor_profiles`** (`Identity` module): source of `vendor_profile_id`; cross-module access via `VendorProfileReader` contract.

> **Schema note on "store":** The user description uses "store" as an alias for `vendor_profile`. The Phase 1 locked schema (`11_DB_Schema.md`) has no `stores` table — one vendor user maps to exactly one `vendor_profile` (1:1). "store_id" in the vendor API maps to `vendor_profile.public_id`.

---

## Success Criteria

### Measurable Outcomes

- **SC-001**: Admin can trigger a complete sale or digital import (select vendor → upload file → submit) in under 30 seconds for files up to 200 rows.
- **SC-002**: Vendor can POST a valid `.xlsx` to the sale or digital import endpoint and receive a clear JSON response within 10 seconds for files up to 200 rows.
- **SC-003**: If any single row is invalid, zero new services appear in the database (full atomicity enforced).
- **SC-004**: All per-row error messages are returned in both English and Arabic.
- **SC-005**: A vendor cannot successfully import services under another vendor's profile (authorization boundary enforced).

---

## Phase Context

| Field | Value |
|---|---|
| **Phase ID** | Phase 6.1 — Sale + Digital Excel Imports |
| **Week** | W7 (1 day) |
| **PRD** | FR-22 |
| **Tables touched** | `excel_imports`, `excel_import_errors` (existing; no schema changes) |
| **Package** | `maatwebsite/excel` (already in `10_Package_List.md`) |
| **Blocks** | None |
| **Blocked by** | Phase 2.4 (rental pattern must exist — provides the import architecture) |

**Cut-list (from Phase 6.1):** None — this IS the deferred-from-Phase-2.4 work.  
**Phase 2 deferrals:** image-folder upload (filename references only for now), async/queued background imports.

---

## Constitution Check

| # | Principle | Status | Notes |
|---|---|---|---|
| I | Modular Monolith | ✅ PASS | Stays in `Catalog` module; cross-module vendor access via `VendorProfileReader` contract |
| II | Three Product Types — `match($enum)` | ✅ PASS | `ImportSaleServicesFromExcelAction` and `ImportDigitalServicesFromExcelAction` are separate per-type classes; no if/elseif chains |
| III | Money Discipline | ✅ PASS | `base_price_minor` stored as BIGINT; validated as `integer` in import rules; no floats |
| IV | Bilingual EN+AR | ✅ PASS | `name_en`/`name_ar`, `short_description_en`/`short_description_ar` required in template; per-row errors are bilingual JSON |
| V | Append-Only Tables | ✅ PASS | `excel_imports`/`excel_import_errors` are not append-only ledger tables; no append-only tables are modified |
| VI | Spec-Driven ADR | ✅ N/A | No new module; Catalog ADR (ADR-0005) already accepted |
| VII | Test-First Critical Paths | ✅ PASS | Pest tests for happy path + rollback written same day for both types |
| VIII | Idempotency | ✅ N/A | Import endpoints are not payment-mutating; idempotency keys not required |
| IX | Domain Events DB::afterCommit | ✅ PASS | Any post-import events (e.g., `ServicesImported`) fire after outer transaction commit; same pattern as Phase 2.4 |
| X | Vendor Approval Two-Step Gate | ✅ PASS | Import actions MUST verify vendor is approved for `sale`/`digital` before processing |
| XI | Document Storage | ✅ N/A | Uploaded Excel files are temporary processing files, not permanent assets; stored locally and discarded post-import |

---

## Assumptions

1. **"Store" = `vendor_profile`**: The Phase 1 locked 60-table schema has no `stores` table. One vendor user has exactly one `vendor_profile` (UNIQUE FK). "Store selection" in the admin modal means selecting a `vendor_profile`; `store_id` in the vendor API maps to `vendor_profile.public_id`.

2. **No schema changes**: `excel_imports` and `excel_import_errors` were created in Phase 2.4 and accommodate `product_type` ENUM values `'sale'` and `'digital'` already. No migrations are needed.

3. **Vendor type approval enforced**: `ImportSaleServicesFromExcelAction` checks `vendor_approved_product_types` for `product_type=sale`; `ImportDigitalServicesFromExcelAction` checks for `product_type=digital` before processing rows.

4. **Admin does not need type approval**: Admins import on behalf of vendors; the vendor's approval status governs which types are valid, not the admin's permissions.

5. **Excel templates**: `sales.xlsx` column structure follows `service_sale_details` fields; `digital.xlsx` follows `service_digital_details` fields. Template files are provided separately (not in scope for this spec).

6. **File type validation**: `.xlsx` (MIME: `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`) and `.xls` (MIME: `application/vnd.ms-excel`) are accepted; all others are rejected.

7. **Reuse of Phase 2.4 pattern**: `ImportSaleServicesFromExcelAction` and `ImportDigitalServicesFromExcelAction` follow the exact same four-phase pattern as `ImportRentalServicesFromExcelAction`: store file → create pending record → validate all rows → atomic commit or logged rollback.

8. **Admin Filament UI implementation**: The admin import is implemented as a header `Action` on `SaleServiceResource` and `DigitalServiceResource` (not as a separate Filament `Page`), consistent with the Filament v3 component reference. The action form contains a vendor selector and file upload field.

---

## Exit Criteria

- [ ] Admin can import `sales.xlsx` from Filament with explicit vendor selection — services appear in `SaleServiceResource` list
- [ ] Admin can import `digital.xlsx` from Filament with explicit vendor selection — services appear in `DigitalServiceResource` list
- [ ] Vendor can POST `sales.xlsx` to `/api/v1/vendor/services/sale/import` and receive a `200` success envelope
- [ ] Vendor can POST `digital.xlsx` to `/api/v1/vendor/services/digital/import` and receive a `200` success envelope
- [ ] A file with any invalid row creates zero services and returns per-row bilingual errors (full rollback enforced)
