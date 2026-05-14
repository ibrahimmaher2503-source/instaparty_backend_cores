# Feature Specification: Admin Booking View

**Feature Branch**: `026-admin-booking-view`  
**Created**: 2026-05-04  
**Status**: Draft  
**Input**: User description: "BookingResource currently only has ListBookings.php. Admin cannot drill into a booking from /admin/bookings. Build full View page with relation managers for vendors, items, addresses, payments, snapshots, state transitions. Resolve raw IDs in list view. Eager-load relations to prevent N+1. Pest green."

## Traceability & Scope

- **Phase ID**: Proposed Phase 10.2 - Admin Booking Resource View. ⚠️ PHASE BACKFILL NEEDED: `docs/specs/09_Phasing_Plan.md` does not currently list Phase 10.2, though the request explicitly positions this work as standard admin resource view coverage that complements Phase 9.4 Booking 360 Forensics.
- **PRD coverage**: FR-16, FR-17, FR-18, FR-30, plus PRD §9 auditability for booking changes and settlement actions.
- **Local requirements**: FR-EXT-001 through FR-EXT-010 below define the standard admin booking drill-down scope because the PRD requires monitoring/facilitation but does not enumerate the exact admin booking detail panels. ⚠️ BACKFILL NEEDED: add this admin resource view detail to `docs/specs/01_PRD.md` or the admin audit specification.
- **Schema traceability**: No new tables. Existing read-only/admin-action scope touches `bookings`, `booking_vendors`, `booking_items`, `booking_addresses`, `booking_snapshots`, `booking_state_transitions`, `payments`, `users`, `vendor_profiles`, `services`, and `occasions` from `docs/specs/11_DB_Schema.md`.
- **ADR required**: None; this is admin visibility over shipped Booking module data and existing intervention actions.
- **API impact**: None. No customer, vendor, public, or admin API endpoint is added or changed.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Drill Into Booking Operations Detail (Priority: P1)

An admin reviewing the bookings list needs to open any booking and inspect the complete operational context in one standard detail view, including vendors, items, addresses, payments, snapshots, and state transitions.

**Why this priority**: Without a drill-down, the admin cannot satisfy PRD FR-16 monitoring duties or investigate the "largest single regression" reported in admin booking operations.

**Independent Test**: Can be fully tested by selecting a booking from the admin bookings list and verifying that the booking detail view opens with all six data sections visible and populated for a seeded multi-vendor booking.

**Acceptance Scenarios**:

1. **Given** an admin is viewing the bookings list, **When** the admin opens a booking row, **Then** the admin sees a booking detail page rather than only the list.
2. **Given** a booking has vendors, items, an address snapshot, payments, snapshots, and state history, **When** the admin opens the booking detail, **Then** each section shows the relevant records without requiring separate manual lookups.
3. **Given** a booking has no records in one related section, **When** the admin opens the booking detail, **Then** the empty section clearly shows there is no related data rather than failing or hiding the whole page.

---

### User Story 2 - Read Booking Relations Without Accidental Mutation (Priority: P1)

An admin needs to inspect booking relations for investigation while preserving immutable historical records and avoiding accidental edits to financial or audit-sensitive data.

**Why this priority**: Booking snapshots and state transitions are audit artifacts. Payments are finance records. The view must support investigation without compromising append-only and status-only invariants.

**Independent Test**: Can be tested by opening each related section and confirming that historical/payment/snapshot/transition data is visible in read-only form, while only approved lightweight booking actions are available from the page header.

**Acceptance Scenarios**:

1. **Given** a booking has versioned snapshots, **When** the admin views the snapshots section, **Then** the admin can inspect each snapshot payload and version metadata without editing it.
2. **Given** a booking has state transitions, **When** the admin views the timeline section, **Then** the admin sees the chronological status movement, actor, trigger, and notes without edit controls.
3. **Given** a booking has payments, **When** the admin views the payments section, **Then** the admin can inspect payment status and navigate to the payment detail without modifying payment rows from the booking view.

---

### User Story 3 - Resolve Human Names In Booking Lists (Priority: P2)

An admin scanning the bookings list needs human-readable customer, occasion, and vendor information instead of raw numeric identifiers.

**Why this priority**: Raw values such as "2 / 1 / 1" slow operations and are ambiguous during customer support or incident review.

**Independent Test**: Can be tested by loading the bookings list with known customer, occasion, and vendor records and verifying that the list displays customer name, phone context, localized occasion name, and vendor business names where applicable.

**Acceptance Scenarios**:

1. **Given** a booking belongs to customer Sara with a phone number, **When** the admin views the bookings list, **Then** the customer column shows Sara's name and makes the phone number available as supporting context.
2. **Given** the admin interface is using English or Arabic, **When** the booking occasion is displayed, **Then** the occasion name appears in the active locale instead of an internal identifier.
3. **Given** a booking includes one or more vendors, **When** vendor information is shown in booking-related lists, **Then** each vendor is represented by business name rather than raw vendor profile ID.

---

### User Story 4 - Keep Booking Admin Screens Fast (Priority: P2)

An admin needs the bookings list and booking detail page to stay responsive even for bookings with multiple vendors, items, payments, snapshots, and transitions.

**Why this priority**: The feature is intended to remove operational friction. It must not introduce slow admin screens or query growth that becomes worse as related records increase.

**Independent Test**: Can be tested by loading a seeded booking with multiple related records and verifying the page renders within the agreed threshold without repeated per-row lookups.

**Acceptance Scenarios**:

1. **Given** a booking has multiple vendors and items, **When** the admin opens the booking detail, **Then** related names and summaries load without repeated lookup growth per row.
2. **Given** the bookings list contains many rows, **When** the admin scans the list, **Then** customer and occasion labels are resolved without visible delay or missing labels.

### Edge Cases

- A booking may have no payment yet; the payment section must show an empty state rather than an error.
- A booking may have no snapshots beyond its initial version; the snapshots section must still identify the available version.
- A booking may contain vendors with missing or untranslated business names; the display must fall back to a safe human-readable label without exposing only a raw ID.
- A booking may have Arabic-only or English-only source content from legacy data; the active locale should be preferred, with a fallback that keeps the page usable.
- A booking may have many state transitions; the timeline must remain readable and ordered from newest-to-oldest or oldest-to-newest consistently.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-EXT-001**: Admin users MUST be able to open a standard booking detail view from the bookings list.
- **FR-EXT-002**: The booking detail view MUST show the booking's vendor split, including vendor business name, vendor status, response deadline, and vendor total.
- **FR-EXT-003**: The booking detail view MUST show booking items, including service label, product type, quantity, line total, and item status with clear status meaning.
- **FR-EXT-004**: The booking detail view MUST show the immutable booking address snapshot in read-only form.
- **FR-EXT-005**: The booking detail view MUST show related payments in read-only form and provide a way to continue to the payment detail view when a payment exists.
- **FR-EXT-006**: The booking detail view MUST show booking snapshots in read-only form, including version, trigger, creator context, timestamp, and payload inspection.
- **FR-EXT-007**: The booking detail view MUST show booking state transitions in read-only chronological form, including transition target, from/to status, trigger, actor, timestamp, and notes.
- **FR-EXT-008**: The booking detail header MUST expose only the lightweight existing admin actions in scope: edit booking, force cancel when allowed, and add admin note.
- **FR-EXT-009**: The bookings list MUST display customer name with phone context and localized occasion name instead of raw internal identifiers.
- **FR-EXT-010**: Booking-related vendor displays MUST use vendor business name instead of raw vendor profile identifiers.
- **FR-EXT-011**: Booking list and detail screens MUST resolve related labels efficiently enough that adding vendors/items does not create repeated lookup growth visible to admins.
- **FR-EXT-012**: The feature MUST NOT add editing of booking line items, booking summary print/export, custom forensics workflows, replacement-vendor selection, new payment mutation flows, or new schema.

### Key Entities *(include if feature involves data)*

- **Booking**: The customer event order under admin review; includes customer, occasion, lifecycle status, payment status, fulfillment status, event timing, totals, and tax invoice fields.
- **Booking Vendor**: A vendor-specific split of a booking; includes vendor identity, response status, response deadline, notes, and vendor-level totals.
- **Booking Item**: A service line under a booking vendor; includes service identity, product type, quantity, timing, money totals, and item fulfillment status.
- **Booking Address**: Immutable event address snapshot captured for the booking.
- **Payment**: Finance record associated with the booking; visible from booking context but not mutated from this view.
- **Booking Snapshot**: Append-only versioned booking read model for investigation and audit.
- **Booking State Transition**: Append-only state history for booking, vendor, or item lifecycle movement.
- **Customer**: The user who owns the booking; shown by name and phone context in admin views.
- **Occasion**: The localized event occasion selected for the booking.
- **Vendor Profile**: The vendor business represented in booking splits.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Admins can open any booking from the bookings list and reach its detail view in under 2 seconds for a booking with at least 3 vendors, 10 items, 3 payments, 5 snapshots, and 10 state transitions in the local test environment.
- **SC-002**: 100% of the six required booking relation sections are visible and populated when the underlying booking has matching records.
- **SC-003**: 0 raw customer, occasion, or vendor internal identifiers are shown in the primary booking list columns or vendor relation display when related records exist.
- **SC-004**: At least one automated test verifies each required relation section loads the correct booking-scoped data.
- **SC-005**: At least one automated test verifies list display uses customer name rather than customer ID.
- **SC-006**: At least one automated test verifies the admin booking detail page avoids repeated lookup growth for related customer, occasion, vendor, service, and booking split labels.

## Assumptions

- Existing Phase 3.1 and Phase 3.2 Booking module data models and relationships are available.
- Existing Phase 6.5 force-cancel and add-admin-note actions are reused if present; this feature does not create a broader intervention workflow.
- Admin authorization and role setup already determine which admins can view bookings and invoke allowed header actions.
- The standard booking detail view is separate from the richer Phase 9.4 Booking 360 Forensics experience; this feature is the everyday resource drill-down.
- No API documentation updates are required because no HTTP API endpoint is added or changed.
- Currency display continues to use integer minor units paired with currency codes; no decimal or floating-point money behavior is introduced.

## Constitution Check

- **I. Modular Monolith**: Pass. Scope stays in the Booking admin surface and reads related module data through existing relationships/contracts where already established.
- **II. Three Product Types**: Pass. Booking item display must cover rental, sale, and digital product types without string-condition branching in implementation planning.
- **III. Money Discipline**: Pass. Totals remain integer minor units with currency codes; no new money arithmetic is required.
- **IV. Bilingual EN+AR**: Pass. Occasion and vendor names must respect active locale with fallback behavior.
- **V. Append-Only Tables**: Pass. Snapshots and state transitions are read-only; payments are read-only from this view.
- **VI. Spec-Driven Development**: Pass. No new module or ADR is required.
- **VII. Test-First Critical Paths**: Pass. Booking admin visibility tests are required, including relation loading and list label resolution.
- **VIII. Idempotency**: Pass. No new state-changing endpoint is introduced.
- **IX. Domain Events After Commit**: Pass. No new domain event is introduced by the view-only portions; existing admin actions retain their existing event rules.
- **X. Vendor Approval Gate**: Pass. Vendor display is observational only and does not alter approval state.
- **XI. Document Storage**: Pass. No document or media storage behavior is introduced.

## Bilingual & Accessibility Notes

- Customer names and phone numbers are not translatable, but their column labels and empty states must be localized.
- Occasion names and vendor business names are translatable and must prefer the active admin locale.
- Status labels for vendor status, item status, payment status, snapshot trigger, and transition trigger must be understandable in both English and Arabic.
- Arabic admin usage must preserve readable RTL ordering for relation sections and timeline content.

## Cut-List

- Editing booking line items from the relation sections is deferred to Phase 1.5.
- Printing or exporting a booking summary is deferred to the Booking 360 Forensics work.
- Deep forensic reconstruction beyond snapshots and state transitions is out of scope for this standard view.

## Exit Criteria

- [ ] Admin clicks any booking row and the booking detail view opens.
- [ ] All six relation sections are available and populated with booking-scoped data when records exist.
- [ ] The bookings list no longer shows raw customer, occasion, or vendor IDs where human labels exist.
- [ ] Automated tests cover page rendering, each relation section, label resolution, and query growth protection.
- [ ] The full relevant automated test suite is green.
