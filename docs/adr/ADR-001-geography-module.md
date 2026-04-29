# ADR-001: Introduce Geography as a First-Class Module

## Status
Accepted

## Context
Geographic data (cities, regions, governorates) was required by:
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

## Consequences
- Clear ownership boundaries
- Cleaner Filament resource discovery
- Easier future expansion (multi-country, GCC)

## Alternatives Considered

### Option B: Put inside Shared
Rejected because:
- Violates bounded context
- Turns Shared into a God module
- Breaks modular isolation
