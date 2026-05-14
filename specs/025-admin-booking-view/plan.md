# Implementation Plan: Admin Booking View

**Branch**: `026-admin-booking-view` | **Date**: 2026-05-04 | **Spec**: [spec.md](./spec.md)  
**Input**: Feature specification from `specs/025-admin-booking-view/spec.md`

## Summary

Build the standard admin booking drill-down for `/admin/bookings` so admins can open a booking row, inspect six relation sections, and see human-readable booking labels instead of raw IDs. The technical approach is to extend the existing Booking Filament resource with a View page, read-only relation managers, list column polish, and explicit eager loading. No schema, API endpoint, package, or ADR is introduced.

## Technical Context

**Language/Version**: PHP 8.3+, Laravel 12  
**Primary Dependencies**: Filament v3, Spatie model states, Spatie translatable, Brick Money, Pest, Larastan, Laravel Pint  
**Storage**: Existing MySQL/MariaDB tables only; no schema change  
**Testing**: Pest feature tests for Filament resource rendering, relation manager data, list label resolution, and query growth guard  
**Target Platform**: Laravel backend/admin panel at `/admin`  
**Project Type**: Modular monolith backend with Filament admin UI  
**Performance Goals**: Admin opens a representative booking detail in under 2 seconds locally; list/detail avoid repeated per-row relationship lookups  
**Constraints**: No new packages; no new tables; no new API endpoints; append-only booking snapshots and booking state transitions remain read-only; payments remain read-only from booking context; no Phase 2 scope  
**Scale/Scope**: One Booking module admin resource view, six relation managers, list label polish, and focused tests

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Modular Monolith**: PASS. Work stays under `app/Modules/Booking/...` except references to existing resources/models needed for display. Add model methods only for relationships, which is allowed by project rules.
- **II. Three Product Types**: PASS. Booking item relation manager displays rental/sale/digital product types and state colors via enum/state-aware mapping. Implementation must not use `if/elseif` on product type strings.
- **III. Money Discipline**: PASS. Money columns are displayed from integer minor units with currency codes; no arithmetic or decimal columns are introduced.
- **IV. Bilingual EN+AR**: PASS. Occasion and vendor translatable names are displayed in the active locale with fallback. New labels/status copy must be added to Booking EN/AR language files.
- **V. Append-Only Tables**: PASS. `booking_snapshots` and `booking_state_transitions` are read-only. `payments` are read-only from this page.
- **VI. Spec-Driven Development**: PASS. No new module, no new ADR. Existing Booking module ADR/scope remains sufficient.
- **VII. Test-First Critical Paths**: PASS. Booking admin visibility is booking-critical; Pest coverage is required before commit.
- **VIII. Idempotency**: PASS. No new HTTP endpoint. Existing state-changing actions keep their current idempotency/event rules.
- **IX. Domain Events After Commit**: PASS. View/read-only sections fire no events. Existing Force Cancel/Add Admin Note actions are reused and must retain their existing after-commit behavior.
- **X. Vendor Approval Gate**: PASS. Vendor data is display-only; no vendor approval state mutation.
- **XI. Document Storage**: PASS. No document/media behavior.

**Gate result**: PASS. No unjustified constitution violations.

## Project Structure

### Documentation (this feature)

```text
specs/025-admin-booking-view/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── admin-booking-view.md
├── checklists/
│   └── requirements.md
└── spec.md
```

### Source Code (repository root)

```text
app/Modules/Booking/
├── Domain/Models/
│   ├── Booking.php
│   ├── BookingVendor.php
│   ├── BookingItem.php
│   ├── BookingSnapshot.php
│   └── BookingStateTransition.php
├── Filament/Resources/
│   ├── BookingResource.php
│   └── BookingResource/
│       ├── Pages/
│       │   ├── ListBookings.php
│       │   └── ViewBooking.php
│       └── RelationManagers/
│           ├── BookingVendorsRelationManager.php
│           ├── BookingItemsRelationManager.php
│           ├── BookingAddressesRelationManager.php
│           ├── PaymentsRelationManager.php
│           ├── BookingSnapshotsRelationManager.php
│           └── BookingStateTransitionsRelationManager.php
└── Resources/lang/
    ├── en/booking.php
    └── ar/booking.php

tests/Feature/Modules/Booking/
└── AdminBookingViewTest.php
```

**Structure Decision**: Use the existing Booking module and Filament resource structure. Do not create a new module, custom dashboard, API route, or forensics page.

## Traceability

- **PRD**: FR-16, FR-17, FR-18, FR-30, PRD §9 auditability.
- **Phase**: Proposed Phase 10.2 Admin Booking Resource View. `docs/specs/09_Phasing_Plan.md` currently lacks Phase 10.2, so this plan carries the spec's phase backfill note.
- **Schema**: Existing `bookings`, `booking_vendors`, `booking_items`, `booking_addresses`, `booking_snapshots`, `booking_state_transitions`, `payments`, `users`, `vendor_profiles`, `services`, `occasions`.
- **API Registry**: No update required; no API endpoint is added or changed.
- **Bruno/Scribe**: No update required; no API endpoint is added or changed.

## Phase 0: Research

Research complete in [research.md](./research.md).

Key decisions:
- Use Filament `ViewRecord` plus relation managers rather than a custom page.
- Add missing Eloquent relationships only where needed for display/eager loading.
- Keep snapshots, transitions, and payments read-only.
- Use locale-aware translatable display helpers/patterns already used in the repo.
- Use query listener/count assertions for N+1 guard instead of adding Debugbar as a hard test dependency.

## Phase 1: Design & Contracts

Design artifacts:
- [data-model.md](./data-model.md)
- [contracts/admin-booking-view.md](./contracts/admin-booking-view.md)
- [quickstart.md](./quickstart.md)

## Implementation Notes

- Register `Pages\ViewBooking::route('/{record}')` in `BookingResource::getPages()`.
- Ensure list rows can navigate to the view page and list actions include a view action if existing table behavior does not expose row click.
- `BookingResource::getEloquentQuery()` should eager-load at least `customer`, `occasion`, and `vendors.vendor`; detail/view queries should include `address`, `vendors.vendor`, `items.service`, `payments`, `snapshots`, and `stateTransitions`.
- If relationships are missing, add relationship-only methods to models:
  - `BookingVendor::vendor()` to `VendorProfile`
  - `BookingItem::service()` to `Service`
  - `Booking::payments()` to `Payment`
  - optional `BookingSnapshot::triggeredBy()` / `BookingStateTransition::triggeredByUser()` for actor labels
- Relation managers must disable create/edit/delete/associate/dissociate actions unless the spec explicitly allows the action.
- Use `match` for status/product-type color mapping. Avoid `if/elseif` on product type strings.
- Payment relation manager should link to `PaymentResource` view only if a payment view page exists; otherwise link to the list/detail route available after PaymentResource is extended.
- Header actions: Edit, Force Cancel, Add Admin Note. Reuse existing actions from Phase 6.5 when present; do not recreate business logic in the page.

## Tests & Quality Gates

- Pest: admin can open a booking from `/admin/bookings` and reach the detail page.
- Pest: each relation manager shows only booking-scoped data and handles empty state.
- Pest: list customer column displays customer name and phone context, not raw customer ID.
- Pest: occasion displays localized name with fallback.
- Pest: vendor relation displays business name, not raw vendor profile ID.
- Pest: booking item relation covers rental, sale, and digital product types.
- Pest: query growth guard for representative booking view/list data.
- Run before commit: `php artisan pint`, `./vendor/bin/phpstan analyse`, `./vendor/bin/pest --bail`.

## Post-Design Constitution Check

- **I. Modular Monolith**: PASS. File layout stays inside Booking module; cross-module references are display relationships to existing domain models, not business workflow calls.
- **II. Three Product Types**: PASS. Tests require rental/sale/digital item coverage; color/label mapping must use enums/state classes and `match`.
- **III. Money Discipline**: PASS. Display-only integer minor units.
- **IV. Bilingual EN+AR**: PASS. Language files and locale-aware names included in plan.
- **V. Append-Only Tables**: PASS. Relation managers are read-only for append-only history.
- **VI. Spec-Driven Development**: PASS. No ADR required for standard resource extension.
- **VII. Test-First Critical Paths**: PASS. Required Pest tests are listed.
- **VIII. Idempotency**: PASS. No new endpoints.
- **IX. Domain Events After Commit**: PASS. No new events; existing action behavior must remain unchanged.
- **X. Vendor Approval Gate**: PASS. Read-only vendor display.
- **XI. Document Storage**: PASS. No storage changes.

**Gate result**: PASS. Ready for `/speckit.tasks`.

## Complexity Tracking

No constitution violations require justification.
