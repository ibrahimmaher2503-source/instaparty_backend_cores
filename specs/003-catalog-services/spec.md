# Feature Specification: Catalog Service Management (Phases 2.0–2.4)

**Feature Branch**: `003-catalog-services`  
**Created**: 2026-04-29  
**Status**: Draft  
**ADR**: [ADR-0004 — Catalog Module](../../docs/adr/0004-catalog-module.md) — Accepted  
**Phases covered**: 2.0 (Foundation), 2.1 (Rental), 2.2 (Sale), 2.3 (Digital + Inventory), 2.4 (Excel Import)

---

## User Scenarios & Testing

### User Story 1 — Vendor Creates a Rental Service (Priority: P1)

A vendor approved for the `rental` product type can submit a new rental service (e.g., an inflatable bounce house) with physical requirements, rental duration, and a refundable security deposit. The service lands in `pending_review` and appears in the admin's Filament queue.

**Why this priority**: This is the first revenue-generating action on the platform. All downstream modules (Booking, Payments) depend on services existing.

**Independent Test**: Can be fully tested by calling `POST /api/v1/vendor/services/rental` as an approved-for-rental vendor and verifying the created service record.

**Acceptance Scenarios**:

1. **Given** a vendor approved for `rental`, **When** they `POST /api/v1/vendor/services/rental` with valid fields (name EN+AR, category, base_price_minor, requires_electricity, setup_time_minutes, security_deposit_minor), **Then** a `services` record with `product_type=rental` and `status=draft` is created, a linked `service_rental_details` row is created, and the response includes `public_id`.
2. **Given** the same vendor, **When** they submit without `default_rental_duration_hours`, **Then** the API returns 422 with a validation error in the request locale.
3. **Given** a vendor NOT approved for `rental`, **When** they call `POST /api/v1/vendor/services/rental`, **Then** the API returns 403.
4. **Given** an unauthenticated caller, **When** they call `POST /api/v1/vendor/services/rental`, **Then** the API returns 401.
5. **Given** a rental service exists, **When** admin views Filament `RentalServiceResource`, **Then** the service appears with `product_type` badge, EN/AR name tabs, and base_price in EGP.

---

### User Story 2 — Vendor Creates a Sale Service (Priority: P1)

A vendor approved for `sale` can list a made-to-order product (e.g., a custom birthday cake) with lead time, customization fields, and stock quantity. Conditional validation enforces `lead_time_hours` whenever `is_made_to_order` is true.

**Why this priority**: Sale is the second most common product type (alongside rental). Blocking Phase 2.2 blocks Phase 2.3 and all Booking work.

**Independent Test**: `POST /api/v1/vendor/services/sale` creates a cake; a second request with `is_made_to_order=true` and no `lead_time_hours` returns 422.

**Acceptance Scenarios**:

1. **Given** a vendor approved for `sale`, **When** they `POST /api/v1/vendor/services/sale` with `is_made_to_order=true` and `lead_time_hours=48`, **Then** a `service_sale_details` row is created with those values.
2. **Given** the same vendor, **When** they submit `is_made_to_order=true` with no `lead_time_hours`, **Then** API returns 422 referencing `lead_time_hours`.
3. **Given** the same vendor, **When** they submit `customization_fields` as a valid JSON object, **Then** it is stored and returned in the API response.
4. **Given** a vendor NOT approved for `sale`, **When** they call the sale endpoint, **Then** 403.

---

### User Story 3 — Vendor Creates a Digital Service (Priority: P2)

A vendor approved for `digital` can list an e-invitation or gift link with delivery method, expiry days, and refund policy. Digital services never block on availability — there is no quantity constraint in Phase 2.3.

**Why this priority**: Digital type completes the three-type coverage required before Booking (Phase 3.1).

**Independent Test**: `POST /api/v1/vendor/services/digital` creates the service; `HoldServiceInventoryAction` called for it returns a `held` reservation without overlap checks.

**Acceptance Scenarios**:

1. **Given** a vendor approved for `digital`, **When** they create a digital service with `delivery_method=email`, **Then** a `service_digital_details` row is created.
2. **Given** the service exists, **When** `HoldServiceInventoryAction` is executed for that digital service, **Then** a `service_inventory_reservations` row is created with `status=held` and `expires_at = now() + 15 minutes`, regardless of any other holds.
3. **Given** a `held` reservation past its `expires_at`, **When** `ReleaseExpiredReservations` command runs, **Then** the reservation transitions to `status=expired`.

---

### User Story 4 — Inventory Guard (Rental Overlap) (Priority: P2)

Rental services must block overlapping holds. If a bouncy castle is already held for Saturday 2pm–8pm, a second hold request for overlapping hours must fail.

**Why this priority**: Prevents overselling before Booking module exists. Blocks Phase 3.1.

**Independent Test**: Create two holds for the same rental service at overlapping times; the second returns an error.

**Acceptance Scenarios**:

1. **Given** a rental service with `qty=1`, **When** `HoldServiceInventoryAction` is called for Saturday 2pm–8pm, **Then** a `held` reservation is created.
2. **Given** that hold exists, **When** `HoldServiceInventoryAction` is called again for Saturday 4pm–10pm (overlapping), **Then** an `InventoryNotAvailableException` is thrown (or equivalent 422 API response).
3. **Given** a sale service with `stock_quantity=5`, **When** 5 holds are created, **Then** a 6th hold fails; releasing one hold allows the 6th to succeed.

---

### User Story 5 — Vendor Bulk-Uploads Rental Services via Excel (Priority: P3)

A vendor can upload an Excel file with multiple rental services in one request. All rows are validated first; if any row is invalid, zero rows are imported and a per-row error report is returned in the vendor's locale.

**Why this priority**: FR-22 requires Excel bulk upload from Day 1. Deferred to P3 as it doesn't block Booking.

**Independent Test**: Upload a 10-row valid file → 10 services created. Upload the same file with row 7 having an empty required field → 0 services created, error on row 7 reported.

**Acceptance Scenarios**:

1. **Given** a valid 10-row Excel file, **When** vendor submits it, **Then** 10 `services` + 10 `service_rental_details` rows are created atomically; response lists all 10 public_ids.
2. **Given** a 10-row file where row 7 has a missing `name_en`, **When** vendor submits it, **Then** zero services are created (transaction rolled back) and the error response shows row 7 with the field error in the vendor's locale.
3. **Given** a successful import, **When** vendor checks their service list, **Then** all 10 services appear in `draft` status.

---

### Edge Cases

- What happens when a vendor submits a service with `category_id` not in their `vendor_approved_product_types` type's allowed categories? → 422 validation error.
- What happens when `base_price_minor` is 0? → Allowed (free services exist); `base_price_currency` must still be `EGP`.
- What happens when a hold's `expires_at` passes and no cleanup job has run? → The reservation remains `held` until the next job run; the overlap check treats it as still active until `status = expired`. (Cleanup is scheduled every minute — stale window is at most 60 seconds.)
- What happens when the same vendor uploads an Excel file twice? → Each run creates new services (no deduplication by default in Phase 2.4; `vendor_sku` uniqueness can be added in Phase 6.1).
- What happens when `name_ar` is missing from a service? → 422 — both EN and AR are required.

---

## Requirements

### Functional Requirements

- **FR-CAT-001**: System MUST enforce category-first service creation — `category_id` is required on all service types.
- **FR-CAT-002**: System MUST store per-type service details in separate tables (`service_rental_details`, `service_sale_details`, `service_digital_details`) — no nullable shared columns for type-specific data.
- **FR-CAT-003**: System MUST reject service creation if the vendor is not approved for the requested `product_type` (403).
- **FR-CAT-004**: System MUST require both `name` EN and AR for every service (422 if either is missing).
- **FR-CAT-005**: System MUST create services in `draft` status; they do not become public until admin publishes them.
- **FR-CAT-006**: System MUST validate `lead_time_hours` as required when `is_made_to_order = true` (sale type only).
- **FR-CAT-007**: System MUST prevent rental inventory overlap: a second hold that overlaps an active `held` or `confirmed` rental reservation MUST fail.
- **FR-CAT-008**: System MUST apply cart hold TTL of 15 minutes and payment hold TTL of 24 hours to all reservation types.
- **FR-CAT-009**: System MUST release expired reservations via a scheduled command (every minute); released reservations transition to `expired` status.
- **FR-CAT-010**: System MUST treat digital services as always-available — no overlap check, no stock decrement.
- **FR-CAT-011**: System MUST store service gallery images via spatie/laravel-medialibrary `gallery` collection (max 11 images).
- **FR-CAT-012**: System MUST support Excel bulk import of rental services with **strict no-partial-commit** — any invalid row aborts the entire import.
- **FR-CAT-013**: Excel import errors MUST be reported per row with the field name and error message in the vendor's locale.
- **FR-CAT-014**: Admin MUST be able to see all services per type in Filament (`RentalServiceResource`, `SaleServiceResource`, `DigitalServiceResource`) with EN/AR locale switcher.
- **FR-CAT-015**: All money values MUST be stored as integer minor units (`base_price_minor` BIGINT + `base_price_currency` CHAR(3)); displayed to admin as formatted EGP via `->money('EGP', divideBy: 100)`.

### Key Entities

- **Service**: Polymorphic base (`services` table). Identified externally by `public_id` (ULID). Has `product_type` discriminator. Owned by a `VendorProfile`. Always belongs to a `Category`.
- **ServiceRentalDetail / ServiceSaleDetail / ServiceDigitalDetail**: 1:1 detail rows keyed by `service_id`. Hold type-specific columns. Cascade-deleted with parent service.
- **ServiceInventoryReservation**: Tracks one hold per (service, time slot, user). Has TTL via `expires_at`. Status machine: `held → confirmed | expired | released`.
- **ExcelImport**: Log entry for each bulk-upload attempt with overall status, row count, and error count.
- **ExcelImportError**: Per-row error record (row number, field, message, locale).

---

## Success Criteria

### Measurable Outcomes

- **SC-001**: A vendor approved for all 3 types can create one service of each type via API in under 10 API calls total.
- **SC-002**: All three Filament resources (Rental, Sale, Digital) render correctly in both EN and AR without layout breakage.
- **SC-003**: An Excel file with 100 valid rental rows is imported in a single request with all 100 services created atomically; a file with 1 invalid row results in 0 imports.
- **SC-004**: A rental overlap hold attempt fails within the same API request, with no orphaned reservation row created.
- **SC-005**: All Pest tests (happy path + auth + authz + validation + locale + all 3 product types) pass in CI.
- **SC-006**: Architecture test passes: no `App\Modules\Catalog` code imports `App\Modules\Booking\Domain\Models` or `App\Modules\Payments\Domain\Models`.

---

## Assumptions

- **Phase 2.0 prerequisite**: `occasions`, `categories`, `occasion_category`, `category_field_schemas`, `service_themes` migrations MUST exist before Phase 2.1 migrations run. If Phase 2.0 has not been implemented, it must be done first (same branch or prior commit).
- `ProductType` enum will be moved from `App\Modules\Shared\Domain\Enums\ProductType` to `App\Modules\Catalog\Domain\Enums\ProductType` as the very first task (ADR-0004 §6.3, Option A).
- `service_inventory_reservations.booking_item_id` FK to `booking_items` is nullable and the constraint is NOT declared in Phase 2 migrations — it will be added in Phase 3.1.
- `code_pool_id` on `service_digital_details` is nullable and left unimplemented (Phase 1.5 extension).
- Pricing tiers UI (`service_pricing_tiers`) is deferred — only `base_price_minor` is exposed in Phase 2.
- Availability blocks management UI is deferred — services are "always available" in Phase 2.
- Excel template columns use `name_en` / `name_ar` flat columns (not JSON) for ease of vendor editing; the importer maps these to the `name` JSON column.
- `excel_imports` and `excel_import_errors` migrations are created in the Catalog module directory for Phase 2.4; module ownership is flagged for formal Imports module ADR in Phase 6.1.
- Sale stock decrement on hold: `service_inventory_reservations` counts active holds against `stock_quantity`; `NULL` stock means unlimited.
