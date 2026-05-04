# Research: Sale + Digital Excel Imports

**Phase**: 6.1 | **Date**: 2026-05-03 | **Status**: Complete — No Clarifications Needed

All patterns are established by Phase 2.4. No external unknowns.

---

## Decision 1 — Importer class shape

**Decision**: `SaleServicesImport` and `DigitalServicesImport` implement `ToCollection + WithHeadingRow` — verbatim copy of `RentalServicesImport`.

**Rationale**: The importer is a pure row collector; all validation logic lives in the Action. No row-by-row execution means the Action controls atomicity.

**Alternatives considered**:
- `ToModel` interface — rejected because it triggers one model creation per row inside the Excel library's loop, making global rollback require savepoint gymnastics.

---

## Decision 2 — Admin import as Filament header `Action` (not a separate `Page`)

**Decision**: Header `Action` with modal form on `SaleServiceResource` and `DigitalServiceResource`.

**Rationale**: Per `filament-components.md §2`, header actions with `->form([...])` are the idiomatic Filament v3 pattern for bulk operations on a resource list. A separate Page (as used in Phase 2.4's vendor-facing `ImportRentalServicesPage`) adds navigation overhead for admin. The modal keeps vendor selection and file upload in a single step.

**Alternatives considered**:
- Separate Filament Page — rejected for admin context; adds unnecessary navigation entry.

---

## Decision 3 — Vendor approval check placement

**Decision**: First line of each import Action's `execute()` method verifies `vendor_approved_product_types` for the relevant `ProductType`.

**Rationale**: Fail fast before file I/O; consistent with how `service.create.sale.own` gates creation in `CreateSaleServiceAction`. The import Action is the application-layer boundary for this check.

---

## Decision 4 — `store_id` semantics in vendor API

**Decision**: `store_id` maps to `vendor_profile.public_id`. The Form Request resolves the authenticated user's `vendorProfile` and compares `public_id`.

**Rationale**: Phase 1 schema has no `stores` table. One vendor user → one `vendor_profile` (1:1 FK UNIQUE). "Store" in the user's language maps to `vendor_profile`.

---

## Decision 5 — No new migrations

**Decision**: The existing `excel_imports.product_type` ENUM column already includes `'sale'` and `'digital'` values alongside `'rental'`.

**Rationale**: `11_DB_Schema.md` §13 defines `excel_imports.product_type` as ENUM('rental','sale','digital'). Phase 2.4 created the migration with all three values. Confirmed: no schema change needed.

---

## Decision 6 — `lead_time_hours` / `expiry_days_after_purchase` conditional validation

**Decision**: Two-pass validation in the import Action: (1) Laravel `Validator::make()` for baseline rules; (2) manual conditional check after the loop per row — if `is_made_to_order=true` and `lead_time_hours` is empty, push a bilingual error.

**Rationale**: Laravel's `required_if` in `Validator::make()` works correctly, but the bilingual error generation loop already iterates over `$errors`. The conditional check is cleanest as a second pass before the global fail-or-proceed decision.
