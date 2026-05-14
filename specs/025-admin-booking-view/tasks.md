# Tasks: Admin Booking View

**Input**: Design documents from `specs/025-admin-booking-view/`  
**Prerequisites**: `plan.md`, `spec.md`, `research.md`, `data-model.md`, `contracts/admin-booking-view.md`, `quickstart.md`  
**Tests**: Required by the feature specification and plan. Write Pest tests before implementation work in each user story phase.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel because it touches different files and has no dependency on incomplete tasks.
- **[Story]**: Maps a task to a user story from `spec.md`.
- Every task includes an exact file path.

## Phase 1: Setup

**Purpose**: Confirm scope, existing resource shape, and test targets before editing.

- [x] T001 Review existing BookingResource list/page configuration in `app/Modules/Booking/Filament/Resources/BookingResource.php`
- [x] T002 Review existing Booking domain relationships in `app/Modules/Booking/Domain/Models/Booking.php`
- [x] T003 [P] Review existing booking factories for seeded relation data in `app/Modules/Booking/Database/Factories/`
- [x] T004 [P] Review existing PaymentResource page support in `app/Modules/Payments/Filament/Resources/PaymentResource.php`
- [x] T005 [P] Review Booking EN/AR translation keys in `app/Modules/Booking/Resources/lang/en/booking.php` and `app/Modules/Booking/Resources/lang/ar/booking.php`

---

## Phase 2: Foundational

**Purpose**: Add relationship-only model support and shared labels needed by all story phases.

**Critical**: No user story implementation should begin until this phase is complete.

- [x] T006 Add `payments()` relationship to `app/Modules/Booking/Domain/Models/Booking.php`
- [x] T007 Add `vendor()` relationship to `app/Modules/Booking/Domain/Models/BookingVendor.php`
- [x] T008 Add `service()` relationship to `app/Modules/Booking/Domain/Models/BookingItem.php`
- [x] T009 [P] Add optional `triggeredBy()` relationship to `app/Modules/Booking/Domain/Models/BookingSnapshot.php`
- [x] T010 [P] Add optional `triggeredByUser()` relationship to `app/Modules/Booking/Domain/Models/BookingStateTransition.php`
- [x] T011 Add shared Booking admin relation labels and empty-state copy to `app/Modules/Booking/Resources/lang/en/booking.php`
- [x] T012 Add shared Booking admin relation labels and empty-state copy to `app/Modules/Booking/Resources/lang/ar/booking.php`

**Checkpoint**: Relationship and translation foundation is ready for independently testable user story work.

---

## Phase 3: User Story 1 - Drill Into Booking Operations Detail (Priority: P1) MVP

**Goal**: Admin can open a booking row and see a standard booking detail page with all six relation sections.

**Independent Test**: Open a seeded booking from `/admin/bookings`; the detail view renders and exposes Booking Vendors, Booking Items, Booking Addresses, Payments, Booking Snapshots, and Booking State Transitions sections.

### Tests for User Story 1

- [x] T013 [US1] Add failing Pest test for opening the Booking view page from the list in `tests/Feature/Modules/Booking/AdminBookingViewTest.php`
- [x] T014 [US1] Add failing Pest test asserting all six relation managers are registered for the Booking view in `tests/Feature/Modules/Booking/AdminBookingViewTest.php`
- [x] T015 [US1] Add failing Pest test asserting empty relation sections do not break the Booking view in `tests/Feature/Modules/Booking/AdminBookingViewTest.php`

### Implementation for User Story 1

- [x] T016 [US1] Create ViewBooking page using Filament `ViewRecord` in `app/Modules/Booking/Filament/Resources/BookingResource/Pages/ViewBooking.php`
- [x] T017 [US1] Register ViewBooking route and relation manager classes in `app/Modules/Booking/Filament/Resources/BookingResource.php`
- [x] T018 [P] [US1] Create BookingVendorsRelationManager skeleton with read-only table in `app/Modules/Booking/Filament/Resources/BookingResource/RelationManagers/BookingVendorsRelationManager.php`
- [x] T019 [P] [US1] Create BookingItemsRelationManager skeleton with read-only table in `app/Modules/Booking/Filament/Resources/BookingResource/RelationManagers/BookingItemsRelationManager.php`
- [x] T020 [P] [US1] Create BookingAddressesRelationManager skeleton with read-only table in `app/Modules/Booking/Filament/Resources/BookingResource/RelationManagers/BookingAddressesRelationManager.php`
- [x] T021 [P] [US1] Create PaymentsRelationManager skeleton with read-only table in `app/Modules/Booking/Filament/Resources/BookingResource/RelationManagers/PaymentsRelationManager.php`
- [x] T022 [P] [US1] Create BookingSnapshotsRelationManager skeleton with read-only table in `app/Modules/Booking/Filament/Resources/BookingResource/RelationManagers/BookingSnapshotsRelationManager.php`
- [x] T023 [P] [US1] Create BookingStateTransitionsRelationManager skeleton with read-only table in `app/Modules/Booking/Filament/Resources/BookingResource/RelationManagers/BookingStateTransitionsRelationManager.php`
- [x] T024 [US1] Add row view action or row URL from the booking list to the view page in `app/Modules/Booking/Filament/Resources/BookingResource.php`

**Checkpoint**: User Story 1 is functional and testable as the MVP.

---

## Phase 4: User Story 2 - Read Booking Relations Without Accidental Mutation (Priority: P1)

**Goal**: Admin can inspect relation data while snapshots, transitions, payments, addresses, vendors, and items remain read-only from the Booking view.

**Independent Test**: Open each relation section and verify booking-scoped records are visible, relation actions do not allow create/edit/delete/associate/dissociate, and payments can navigate to payment detail where supported.

### Tests for User Story 2

- [x] T025 [US2] Add failing Pest test asserting Booking Vendors relation shows vendor status, response deadline, and totals in `tests/Feature/Modules/Booking/AdminBookingViewTest.php`
- [x] T026 [US2] Add failing Pest test asserting Booking Items relation shows service, product type, quantity, line total, and item status for rental, sale, and digital in `tests/Feature/Modules/Booking/AdminBookingViewTest.php`
- [x] T027 [US2] Add failing Pest test asserting Addresses, Payments, Snapshots, and State Transitions relations are read-only in `tests/Feature/Modules/Booking/AdminBookingViewTest.php`
- [x] T028 [US2] Add failing Pest test asserting Payments relation links to the PaymentResource view in `tests/Feature/Modules/Booking/AdminBookingViewTest.php`

### Implementation for User Story 2

- [x] T029 [US2] Implement BookingVendorsRelationManager columns and disable mutation actions in `app/Modules/Booking/Filament/Resources/BookingResource/RelationManagers/BookingVendorsRelationManager.php`
- [x] T030 [US2] Implement BookingItemsRelationManager columns with enum/state color mapping via `match` in `app/Modules/Booking/Filament/Resources/BookingResource/RelationManagers/BookingItemsRelationManager.php`
- [x] T031 [US2] Implement BookingAddressesRelationManager read-only snapshot columns in `app/Modules/Booking/Filament/Resources/BookingResource/RelationManagers/BookingAddressesRelationManager.php`
- [x] T032 [US2] Implement PaymentsRelationManager read-only payment columns and PaymentResource view link in `app/Modules/Booking/Filament/Resources/BookingResource/RelationManagers/PaymentsRelationManager.php`
- [x] T033 [US2] Add PaymentResource view page if missing in `app/Modules/Payments/Filament/Resources/PaymentResource/Pages/ViewPayment.php`
- [x] T034 [US2] Register PaymentResource view route if missing in `app/Modules/Payments/Filament/Resources/PaymentResource.php`
- [x] T035 [US2] Implement BookingSnapshotsRelationManager with readable JSON payload display in `app/Modules/Booking/Filament/Resources/BookingResource/RelationManagers/BookingSnapshotsRelationManager.php`
- [x] T036 [US2] Implement BookingStateTransitionsRelationManager chronological timeline columns in `app/Modules/Booking/Filament/Resources/BookingResource/RelationManagers/BookingStateTransitionsRelationManager.php`
- [x] T037 [US2] Add Edit, Force Cancel, and Add Admin Note header actions reusing existing behavior in `app/Modules/Booking/Filament/Resources/BookingResource/Pages/ViewBooking.php`

**Checkpoint**: User Stories 1 and 2 work independently without unsafe relation mutation.

---

## Phase 5: User Story 3 - Resolve Human Names In Booking Lists (Priority: P2)

**Goal**: Admin list/detail displays customer, occasion, and vendor business names instead of raw internal IDs.

**Independent Test**: Load known booking data and confirm customer name with phone context, localized occasion name, and vendor business names appear in admin displays.

### Tests for User Story 3

- [x] T038 [US3] Add failing Pest test asserting customer column shows name and phone context instead of customer ID in `tests/Feature/Modules/Booking/AdminBookingViewTest.php`
- [x] T039 [US3] Add failing Pest test asserting occasion column uses active locale with fallback in `tests/Feature/Modules/Booking/AdminBookingViewTest.php`
- [x] T040 [US3] Add failing Pest test asserting booking vendor display uses translated vendor business name in `tests/Feature/Modules/Booking/AdminBookingViewTest.php`

### Implementation for User Story 3

- [x] T041 [US3] Update customer column display and phone tooltip/description in `app/Modules/Booking/Filament/Resources/BookingResource.php`
- [x] T042 [US3] Update occasion column display to use active locale with fallback in `app/Modules/Booking/Filament/Resources/BookingResource.php`
- [x] T043 [US3] Update BookingResource eager loading to include `vendors.vendor` for vendor name display in `app/Modules/Booking/Filament/Resources/BookingResource.php`
- [x] T044 [US3] Update BookingVendorsRelationManager vendor name fallback behavior in `app/Modules/Booking/Filament/Resources/BookingResource/RelationManagers/BookingVendorsRelationManager.php`
- [x] T045 [US3] Add missing EN/AR column labels for customer phone and localized relation names in `app/Modules/Booking/Resources/lang/en/booking.php` and `app/Modules/Booking/Resources/lang/ar/booking.php`

**Checkpoint**: User Stories 1, 2, and 3 work independently and no raw primary IDs remain when related records exist.

---

## Phase 6: User Story 4 - Keep Booking Admin Screens Fast (Priority: P2)

**Goal**: Booking list and detail screens stay responsive by eager-loading display relationships and avoiding N+1 growth.

**Independent Test**: Load representative booking data with multiple related records and verify query count stays bounded for list and detail rendering.

### Tests for User Story 4

- [x] T046 [US4] Add failing Pest query-count guard for Booking list customer, occasion, and vendor labels in `tests/Feature/Modules/Booking/AdminBookingViewTest.php`
- [x] T047 [US4] Add failing Pest query-count guard for Booking view relation managers with vendors, items, payments, snapshots, and transitions in `tests/Feature/Modules/Booking/AdminBookingViewTest.php`

### Implementation for User Story 4

- [x] T048 [US4] Add list-level eager loading for `customer`, `occasion`, and `vendors.vendor` in `app/Modules/Booking/Filament/Resources/BookingResource.php`
- [x] T049 [US4] Add view/detail eager loading for `address`, `vendors.vendor`, `items.service`, `payments`, `snapshots`, and `stateTransitions` in `app/Modules/Booking/Filament/Resources/BookingResource/Pages/ViewBooking.php`
- [x] T050 [US4] Add relation-manager query eager loading for vendor/service/actor relationships in `app/Modules/Booking/Filament/Resources/BookingResource/RelationManagers/`
- [x] T051 [US4] Refactor duplicated locale fallback formatting into local closures or private static helpers in `app/Modules/Booking/Filament/Resources/BookingResource.php`

**Checkpoint**: All user stories are independently functional and query growth protection passes.

---

## Final Phase: Polish & Cross-Cutting Concerns

**Purpose**: Final validation, quality gates, and task bookkeeping.

- [x] T052 Run focused quickstart verification for `/admin/bookings` using `specs/025-admin-booking-view/quickstart.md`
- [x] T053 Run `php artisan pint` using formatting configuration in `pint.json`
- [x] T054 Run `./vendor/bin/phpstan analyse` using static analysis configuration in `phpstan.neon`
- [x] T055 Run `./vendor/bin/pest --bail` for `tests/Feature/Modules/Booking/AdminBookingViewTest.php`
- [x] T056 Mark completed tasks as `[x]` in `specs/025-admin-booking-view/tasks.md`

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies; can start immediately.
- **Foundational (Phase 2)**: Depends on Setup; blocks all user story phases.
- **US1 (Phase 3)**: Depends on Foundational; MVP.
- **US2 (Phase 4)**: Depends on US1 page/manager skeletons; independently verifies read-only relation behavior.
- **US3 (Phase 5)**: Depends on Foundational and can start after US1 if the list/page exists; integrates with BookingVendorsRelationManager from US1/US2.
- **US4 (Phase 6)**: Depends on relation/list implementation from US1-US3.
- **Polish**: Depends on desired user stories being complete.

### User Story Dependencies

- **US1 Drill Into Booking Operations Detail**: First MVP; no dependency on other user stories after Foundational.
- **US2 Read Booking Relations Without Accidental Mutation**: Depends on US1 skeletons but can be validated without US3/US4.
- **US3 Resolve Human Names In Booking Lists**: Depends on Foundational relationships and list/view availability; can be validated without US4.
- **US4 Keep Booking Admin Screens Fast**: Depends on the completed list/detail/relation display behavior it measures.

### Within Each User Story

- Tests must be added before implementation tasks in that story.
- Relationship methods before relation manager display columns.
- Relation manager skeletons before relation manager column completion.
- Core display behavior before query-count optimization.
- Story checkpoint must pass before moving to the next priority when working sequentially.

## Parallel Opportunities

- T003, T004, and T005 can run in parallel during Setup.
- T009 and T010 can run in parallel after T006-T008 if actor labels are needed.
- T018 through T023 can run in parallel after T016-T017 because each creates a different relation manager file.
- T029 through T036 mostly touch different files and can be split between workers after US2 tests are written, except T032 depends on T033-T034 if PaymentResource view support is missing.
- T041 and T042 both touch `BookingResource.php`, so they should not run in parallel with each other.
- T048 and T051 both touch `BookingResource.php`, so they should not run in parallel with each other.

## Parallel Example: User Story 1

```text
Task: "Create BookingVendorsRelationManager skeleton with read-only table in app/Modules/Booking/Filament/Resources/BookingResource/RelationManagers/BookingVendorsRelationManager.php"
Task: "Create BookingItemsRelationManager skeleton with read-only table in app/Modules/Booking/Filament/Resources/BookingResource/RelationManagers/BookingItemsRelationManager.php"
Task: "Create BookingAddressesRelationManager skeleton with read-only table in app/Modules/Booking/Filament/Resources/BookingResource/RelationManagers/BookingAddressesRelationManager.php"
Task: "Create PaymentsRelationManager skeleton with read-only table in app/Modules/Booking/Filament/Resources/BookingResource/RelationManagers/PaymentsRelationManager.php"
Task: "Create BookingSnapshotsRelationManager skeleton with read-only table in app/Modules/Booking/Filament/Resources/BookingResource/RelationManagers/BookingSnapshotsRelationManager.php"
Task: "Create BookingStateTransitionsRelationManager skeleton with read-only table in app/Modules/Booking/Filament/Resources/BookingResource/RelationManagers/BookingStateTransitionsRelationManager.php"
```

## Parallel Example: User Story 2

```text
Task: "Implement BookingVendorsRelationManager columns and disable mutation actions in app/Modules/Booking/Filament/Resources/BookingResource/RelationManagers/BookingVendorsRelationManager.php"
Task: "Implement BookingItemsRelationManager columns with enum/state color mapping via match in app/Modules/Booking/Filament/Resources/BookingResource/RelationManagers/BookingItemsRelationManager.php"
Task: "Implement BookingAddressesRelationManager read-only snapshot columns in app/Modules/Booking/Filament/Resources/BookingResource/RelationManagers/BookingAddressesRelationManager.php"
Task: "Implement BookingSnapshotsRelationManager with readable JSON payload display in app/Modules/Booking/Filament/Resources/BookingResource/RelationManagers/BookingSnapshotsRelationManager.php"
Task: "Implement BookingStateTransitionsRelationManager chronological timeline columns in app/Modules/Booking/Filament/Resources/BookingResource/RelationManagers/BookingStateTransitionsRelationManager.php"
```

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Setup.
2. Complete Foundational model relationships and translations.
3. Add the Booking View page and six relation manager skeletons.
4. Validate US1 tests and manually open `/admin/bookings/{record}`.

### Incremental Delivery

1. US1 delivers the missing drill-down page.
2. US2 makes relation sections complete and safe for audit/finance-sensitive data.
3. US3 removes raw IDs from admin workflows.
4. US4 locks in performance and N+1 protection.
5. Final phase runs quickstart, Pint, PHPStan, and Pest.

### Single-Developer Strategy

Work sequentially in task order. Avoid starting US4 before US1-US3 are stable because the performance tests measure the completed display behavior.

## Notes

- No new migrations, API routes, Bruno collection entries, Scribe docs, packages, or ADRs are required.
- Do not implement line-item editing, booking summary print/export, replacement-vendor selection, or Booking 360 Forensics behavior in this feature.
- Do not use `if/elseif` on product type strings; use the canonical enum and `match`.
- Keep models limited to relationships/casts/scopes only.
