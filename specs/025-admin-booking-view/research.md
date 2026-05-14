# Research: Admin Booking View

## Decision: Use the standard Booking Filament Resource view page

**Rationale**: The user explicitly differentiates this feature from Booking 360 Forensics. The resource view is the everyday admin drill-down from `/admin/bookings`, so Filament `ViewRecord` plus relation managers matches the requested surface and existing admin patterns.

**Alternatives considered**:
- Custom forensics page: rejected because it duplicates/deviates from the stated Phase 9.4 forensics scope.
- Separate dashboard widget: rejected because the regression is inability to drill into a booking row.

## Decision: Add only relationship methods needed for display and eager loading

**Rationale**: Current models already expose `Booking::customer()`, `Booking::occasion()`, `Booking::address()`, `Booking::vendors()`, `Booking::items()`, `Booking::snapshots()`, and `Booking::stateTransitions()`. The plan may need display-only relationships such as `BookingVendor::vendor()`, `BookingItem::service()`, and `Booking::payments()`. Model relationship methods are explicitly allowed by project rules.

**Alternatives considered**:
- Querying related tables manually in relation managers: rejected because it makes N+1 protection harder and bypasses local model patterns.
- Creating repositories for read-only admin display: rejected as unnecessary abstraction for direct Filament relation display.

## Decision: Keep finance and history relation managers read-only

**Rationale**: `booking_snapshots` and `booking_state_transitions` are append-only audit/history tables. `payments` are mutable only in specific status/log fields and should not be edited from a Booking view. Read-only relation managers satisfy investigation needs without violating append-only and finance rules.

**Alternatives considered**:
- Inline edit actions in relation managers: rejected by spec cut-list and append-only invariants.
- Payment retry/refund actions from this relation manager: rejected because payment operations belong to Payment/Ops flows, not standard booking drill-down.

## Decision: Use locale-aware translatable labels with fallback

**Rationale**: The repo already uses Spatie translatable JSON fields for `occasions.name` and `vendor_profiles.business_name`. Admin screens must work in EN and AR. Display should prefer active locale and fall back to English or first available value when legacy data is incomplete.

**Alternatives considered**:
- Display raw JSON: rejected because admins need readable labels.
- Force English-only: rejected because Arabic is primary and RTL is mandatory.

## Decision: Use query count/listener assertions for N+1 guard

**Rationale**: The request mentions Debugbar, but tests should not require a browser debug panel. Laravel query listeners or database query count assertions can verify query growth without adding a package or relying on UI tooling. `docs/specs/10_Package_List.md` already includes approved dev quality packages and no new package is needed.

**Alternatives considered**:
- Add Laravel Debugbar as a new dependency: rejected because it is not in the locked package list shown for this project and would be overkill for automated tests.
- Manual visual inspection only: rejected because the spec requires automated N+1 protection.

## Decision: Treat Phase 10.2 as a phasing backfill

**Rationale**: The user provided Phase 10.2 context, but the current `docs/specs/09_Phasing_Plan.md` does not list that phase. The plan keeps the requested scope and records the mismatch explicitly rather than blocking implementation.

**Alternatives considered**:
- Reassign to Phase 6.5: rejected because Phase 6.5 is admin override/intervention, while this feature is the standard resource view.
- Block planning: rejected because the spec already marks the required backfill and scope is clear.
