# ADR-0004: Geography Module Review Compatibility Record

## Status
Accepted

## Context
The Phase 0.1 review checklist references `docs/adr/ADR-0004-geography-module.md`.
The canonical Geography ADR in this repository is `docs/adr/ADR-001-geography-module.md`; this compatibility record mirrors that accepted decision so automated and manual review checklists can resolve the referenced path without renumbering existing ADRs.

Geographic data (cities, regions, governorates) is required by:
- Vendor coverage
- Discovery filters
- Booking location
- Reporting aggregation

It was previously undefined in module ownership.

## Decision
Introduce a dedicated Geography module responsible for:
- Location hierarchy
- Translations
- Vendor coverage pivot

The Geography module owns the `countries -> governorates -> regions -> cities` hierarchy, with Phase 1 activating Egypt data only.

## Consequences
- Clear ownership boundaries
- Cleaner Filament resource discovery
- Easier future expansion for multi-country and GCC support
- Customer, vendor, and public APIs remain out of scope until explicitly added with API registry and Bruno documentation

## Alternatives

### Option B: Put inside Shared
Rejected because:
- Violates bounded context
- Turns Shared into a God module
- Breaks modular isolation

## Internal Decisions

### Four-level hierarchy starts with Country
Geography owns `countries -> governorates -> regions -> cities` even though Phase 1 only activates Egypt.
This keeps Phase 1 single-country while preserving a clean schema path for later country expansion without changing existing foreign keys.

### Cities denormalize `governorate_id`
`cities` stores both `region_id` and `governorate_id`.
The canonical hierarchy is still `Region -> Governorate`, but the denormalized key keeps common filters fast for Discovery, vendor coverage, customer addresses, and Reporting.
Application and factory code must keep `cities.governorate_id` aligned with `cities.region.governorate_id`.

### Cross-module reads go through a contract
Other modules must consume `GeographyRepository` instead of importing Geography Eloquent models directly.
This follows the modular-monolith boundary rule and keeps lookup behavior centralized behind `Domain/Contracts`.

### Egypt seed data is a stable baseline
`EgyptGeographySeeder` seeds Egypt plus all 27 governorates with EN and AR names.
Rows are keyed by stable country ISO code, governorate code, and English region/city names so the seeder can run repeatedly without duplicates or `public_id` churn.

### Filament is the only current Geography UI
Phase 0.1 exposes Geography through Filament resources, not public API routes.
If customer/vendor/public API endpoints are added later, they must use the standard `ApiResponse` envelope, locale-aware resources, API registry rows, and Bruno documentation.
