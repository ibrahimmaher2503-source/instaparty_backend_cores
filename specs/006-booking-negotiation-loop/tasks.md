# Tasks: Booking Negotiation Loop

**Input**: Design documents from `specs/006-booking-negotiation-loop/`
**Branch**: `006-booking-negotiation-loop`
**Phase**: 3.2 — Booking Negotiation (Week 4–5)

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: Maps to user story (US1–US5) from spec.md
- Exact file paths are required in every description

---

## Phase 1: Setup (Enums + Migrations)

**Purpose**: New enum types and database schema required by ALL user stories. Must complete before any model or action work begins.

- [X] T001 [P] Create `ModificationStatus` enum in `app/Modules/Booking/Domain/Enums/ModificationStatus.php` with cases: `Pending='pending'`, `CustomerAccepted='customer_accepted'`, `CustomerRejected='customer_rejected'`, `Withdrawn='withdrawn'`, `Expired='expired'`
- [X] T002 [P] Create `ModificationProposalKind` enum in `app/Modules/Booking/Domain/Enums/ModificationProposalKind.php` with cases: `AddItem='add_item'`, `RemoveItem='remove_item'`, `ChangeQuantity='change_quantity'`, `ChangePrice='change_price'`, `ChangeSlot='change_slot'`, `AddSurcharge='add_surcharge'`, `AddNote='add_note'`
- [X] T003 [P] Create `ModificationChangeKind` enum in `app/Modules/Booking/Domain/Enums/ModificationChangeKind.php` with cases: `Add='add'`, `Remove='remove'`, `Update='update'`
- [X] T004 [P] Create migration `app/Modules/Booking/Database/Migrations/2026_05_01_000009_create_booking_modifications_table.php`: `id`, `public_id` CHAR(26) UNIQUE, `booking_vendor_id` FK→booking_vendors restrictOnDelete, `proposed_by` FK→users restrictOnDelete, `proposal_kind` ENUM (7 values), `status` ENUM (5 values) default `'pending'`, `customer_decision_at` TIMESTAMP NULL, `expires_at` TIMESTAMP NULL, `vendor_explanation` JSON NULL, `diff_snapshot` JSON NOT NULL, timestamps; indexes: `(booking_vendor_id, status)`, `(status, expires_at)`
- [X] T005 [P] Create migration `app/Modules/Booking/Database/Migrations/2026_05_01_000010_create_booking_modification_items_table.php`: `id`, `booking_modification_id` FK→booking_modifications cascadeOnDelete, `target_booking_item_id` FK→booking_items NULL nullOnDelete, `change_kind` ENUM('add','remove','update'), `payload` JSON NOT NULL, `created_at` TIMESTAMP useCurrent (NO updated_at — append-only); indexes: `booking_modification_id`, `target_booking_item_id`

---

## Phase 2: Foundational (Models, Events, Infrastructure)

**Purpose**: Core domain objects and events required by multiple user stories. MUST complete before user story phases begin.

⚠️ **CRITICAL**: No user story work can begin until Phase 2 is complete.

- [X] T006 [P] Create `BookingModification` Eloquent model in `app/Modules/Booking/Domain/Models/BookingModification.php`: `@property` docblocks for all columns, `$fillable`, `$casts` (proposal_kind→ModificationProposalKind, status→ModificationStatus, vendor_explanation→array, diff_snapshot→array, dates), relations: `bookingVendor()` BelongsTo, `items()` HasMany BookingModificationItem, `proposedBy()` BelongsTo User
- [X] T007 [P] Create `BookingModificationItem` Eloquent model in `app/Modules/Booking/Domain/Models/BookingModificationItem.php`: `@property` docblocks, `$fillable`, `$casts` (change_kind→ModificationChangeKind, payload→array), append-only (no `updated_at`, no `$timestamps = ['created_at']`), relations: `modification()` BelongsTo BookingModification, `targetItem()` BelongsTo BookingItem nullable
- [X] T008 [P] Create `BookingSubmittedToVendor` event in `app/Modules/Booking/Domain/Events/BookingSubmittedToVendor.php`: constructor `(public readonly BookingVendor $bookingVendor, public readonly Booking $booking)`
- [X] T009 [P] Create `VendorAccepted` event in `app/Modules/Booking/Domain/Events/VendorAccepted.php`: constructor `(public readonly BookingVendor $bookingVendor)`
- [X] T010 [P] Create `VendorModificationProposed` event in `app/Modules/Booking/Domain/Events/VendorModificationProposed.php`: constructor `(public readonly BookingModification $modification)`
- [X] T011 [P] Create `VendorRejected` event in `app/Modules/Booking/Domain/Events/VendorRejected.php`: constructor `(public readonly BookingVendor $bookingVendor)`
- [X] T012 [P] Create `CustomerModificationDecided` event in `app/Modules/Booking/Domain/Events/CustomerModificationDecided.php`: constructor `(public readonly BookingModification $modification, public readonly string $decision)`
- [X] T013 [P] Create `BookingConfirmed` event in `app/Modules/Booking/Domain/Events/BookingConfirmed.php`: constructor `(public readonly Booking $booking)`
- [X] T014 [P] Create `BookingCancelled` event in `app/Modules/Booking/Domain/Events/BookingCancelled.php`: constructor `(public readonly Booking $booking)`
- [X] T015 [P] Create `BookingModificationResource` in `app/Modules/Booking/Http/Resources/BookingModificationResource.php`: `@mixin BookingModification`, returns `public_id`, `booking_vendor_id`, `proposal_kind` (→value), `status` (→value), `vendor_explanation`, `diff_snapshot`, `created_at` ISO8601
- [X] T016 [P] Create `app/Modules/Booking/Routes/vendor.php` scaffold with Sanctum auth middleware and `role:vendor` middleware group; no routes yet (routes added per story)
- [X] T017 [P] Create `app/Modules/Booking/Routes/admin.php` scaffold with Sanctum auth middleware and `role:admin` middleware group; no routes yet
- [X] T018 Update `app/Modules/Booking/Providers/BookingServiceProvider.php`: register vendor.php and admin.php routes in `boot()`; add all new migrations path (already handled by `loadMigrationsFrom`); prepare listener registration array for new events (listeners registered in US phases)

**Checkpoint**: Run `php artisan migrate` — `booking_modifications` and `booking_modification_items` tables must exist. All models must instantiate without errors.

---

## Phase 3: User Story 1 — Customer Submits Booking (P1) 🎯 MVP

**Goal**: Customer can submit a draft booking, transitioning it to `vendor_review` with per-vendor tasks and response deadlines. Full idempotency on submit.

**Independent Test**: `php artisan test tests/Feature/Modules/Booking/SubmitBookingTest.php` — all scenarios in quickstart.md Scenario 1 (steps 1–3 and idempotency check).

- [X] T019 [P] [US1] Create `SubmitBookingDTO` in `app/Modules/Booking/Application/DTOs/SubmitBookingDTO.php`: `readonly` class, constructor `(public int $bookingId, public int $customerId, public string $idempotencyKey)`
- [X] T020 [US1] Create `SubmitBookingAction` in `app/Modules/Booking/Application/Actions/SubmitBookingAction.php`: `execute(SubmitBookingDTO $dto)` returns `Booking`; check idempotency_keys table first; inside `DB::transaction`: validate booking is `draft` and belongs to customer; validate booking has ≥1 item; set `lifecycle_status='submitted'` then `'vendor_review'`; for each `booking_vendor`: set `sub_status='pending'`, `response_deadline=now()->addHours(24)`; append `booking_state_transitions` rows; `DB::afterCommit` fires `BookingSubmittedToVendor` per vendor; store idempotency key result
- [X] T021 [P] [US1] Create `SubmitBookingRequest` in `app/Modules/Booking/Http/Requests/SubmitBookingRequest.php`: `authorize()` true; `rules()` `@return array<string,mixed>` returning `[]` (no body needed); add `idempotencyKey()` helper reading `Idempotency-Key` header, validating UUID format, aborting 422 if missing
- [X] T022 [US1] Create `BookingNegotiationController` in `app/Modules/Booking/Http/Controllers/Customer/BookingNegotiationController.php`: `submit(SubmitBookingRequest $request, string $bookingPublicId)` — 3-line body: resolve booking via `BookingRepository`, call `SubmitBookingAction::execute()`, return `ApiResponse::success(new BookingResource($booking))`
- [X] T023 [US1] Add submit route to `app/Modules/Booking/Routes/customer.php`: `POST bookings/{booking_public_id}/submit` → `BookingNegotiationController@submit`
- [X] T024 [US1] Create `WriteNegotiationSnapshotListener` in `app/Modules/Booking/Application/Listeners/WriteNegotiationSnapshotListener.php`: `handle(BookingSubmittedToVendor|VendorAccepted|VendorModificationProposed|VendorRejected|CustomerModificationDecided|BookingConfirmed|BookingCancelled $event)` — resolves booking from event, inserts new `booking_snapshots` row with incremented version and `trigger_kind='vendor_responded'`/`'customer_decided'`/etc.
- [X] T025 [US1] Register `WriteNegotiationSnapshotListener` for `BookingSubmittedToVendor` in `BookingServiceProvider`; also wire `WriteBookingStateTransitionListener` (existing) to `BookingSubmittedToVendor`
- [X] T026 [US1] Write Pest feature test `tests/Feature/Modules/Booking/SubmitBookingTest.php`: test groups `['booking','negotiation']`; cover: happy path submit (lifecycle_status=vendor_review, booking_vendor sub_status=pending, response_deadline set, state_transition row created); submit empty draft → 422; submit non-draft → 409; submit another customer's booking → 403; unauthenticated → 401; idempotency: duplicate key returns same response, no duplicate state_transitions

**Checkpoint**: `./vendor/bin/pest tests/Feature/Modules/Booking/SubmitBookingTest.php` — all assertions pass.

---

## Phase 4: User Story 2 — Vendor Accepts Booking (P2)

**Goal**: Vendor can accept their assigned portion. When all vendors accept, booking confirms. Rental inventory reservations upgrade to `confirmed`.

**Independent Test**: `./vendor/bin/pest tests/Feature/Modules/Booking/VendorAcceptTest.php` — single-vendor and multi-vendor accept scenarios from quickstart.md Scenario 1 (steps 4–5).

- [X] T027 [US2] Create `VendorAcceptBookingAction` in `app/Modules/Booking/Application/Actions/VendorAcceptBookingAction.php`: `execute(int $bookingVendorId, int $vendorProfileId)` returns `BookingVendor`; inside `DB::transaction`: load `BookingVendor` with lock, validate `sub_status=pending` and belongs to vendor; set `sub_status='accepted'`, `responded_at=now()`; append `booking_state_transitions`; check if ALL `booking_vendor.sub_status='accepted'` for this booking — if so: set `booking.lifecycle_status='confirmed'`, `confirmed_at=now()`, append booking state_transition; `DB::afterCommit` fires `VendorAccepted($bookingVendor)` and optionally `BookingConfirmed($booking)` if all accepted
- [X] T028 [US2] Create `Vendor/BookingController` in `app/Modules/Booking/Http/Controllers/Vendor/BookingController.php`: `accept(string $bookingVendorPublicId)` — 3-line body resolving booking_vendor by public_id, calling VendorAcceptBookingAction, returning `ApiResponse::success(new BookingVendorResource($bookingVendor))`; `index()` for listing pending vendor tasks
- [X] T029 [US2] Add vendor routes to `app/Modules/Booking/Routes/vendor.php`: `GET booking-vendors` → `Vendor/BookingController@index`; `POST booking-vendors/{bv_public_id}/accept` → `Vendor/BookingController@accept`
- [X] T030 [US2] Create `ConfirmInventoryReservationsListener` in `app/Modules/Booking/Application/Listeners/ConfirmInventoryReservationsListener.php`: `handle(BookingConfirmed $event)` — uses `DB::table('service_inventory_reservations')` (no cross-module Eloquent); joins `booking_items` on booking_id to find all Rental item reservations with `status='held'`; updates them to `status='confirmed'`
- [X] T031 [US2] Register in `BookingServiceProvider`: `VendorAccepted` → `WriteNegotiationSnapshotListener`; `BookingConfirmed` → `WriteNegotiationSnapshotListener`, `ConfirmInventoryReservationsListener`; `VendorAccepted` → `WriteBookingStateTransitionListener`; `BookingConfirmed` → `WriteBookingStateTransitionListener`
- [X] T032 [US2] Write Pest feature test `tests/Feature/Modules/Booking/VendorAcceptTest.php`: group `['booking','negotiation']`; cover: single vendor accept → booking confirmed; multi-vendor partial accept (first vendor accepts but second still pending → booking stays vendor_review); multi-vendor all accept → confirmed; Rental inventory reservation status = 'confirmed' after booking confirmed; vendor accepts wrong booking_vendor → 403; accept non-pending booking_vendor → 409; unauthenticated → 401; per-type: Rental, Sale, Digital

**Checkpoint**: `./vendor/bin/pest tests/Feature/Modules/Booking/VendorAcceptTest.php` — all assertions pass.

---

## Phase 5: User Story 3 — Vendor Proposes Modifications (P2)

**Goal**: Vendor proposes item changes with diff_snapshot. Booking moves to customer_review. Exactly one pending modification per booking_vendor enforced.

**Independent Test**: `./vendor/bin/pest tests/Feature/Modules/Booking/VendorModifyTest.php` — quickstart.md Scenario 2 (steps 3–4) and Scenario 6.

- [X] T033 [P] [US3] Create `VendorModifyDTO` in `app/Modules/Booking/Application/DTOs/VendorModifyDTO.php`: `readonly` class, constructor `(public int $bookingVendorId, public int $vendorProfileId, public ModificationProposalKind $proposalKind, public array $changes, public ?array $vendorExplanation)`; `$changes` is `array<int, array{change_kind: string, target_item_public_id?: string, payload: array<string,mixed>}>`
- [X] T034 [US3] Create `VendorModifyBookingAction` in `app/Modules/Booking/Application/Actions/VendorModifyBookingAction.php`: `execute(VendorModifyDTO $dto)` returns `BookingModification`; inside `DB::transaction`: load BookingVendor with lock, validate sub_status=pending and belongs to vendor; check no pending modification exists (409 if one does); build `diff_snapshot` from current booking_items (before) vs proposed payload (after); create `BookingModification` row (status=pending); create `BookingModificationItem` rows for each change; set `booking_vendor.sub_status='modified'`, `responded_at=now()`; set `booking.lifecycle_status='customer_review'`; append state_transitions; `DB::afterCommit` fires `VendorModificationProposed($modification)`
- [X] T035 [P] [US3] Create `VendorModifyRequest` in `app/Modules/Booking/Http/Requests/VendorModifyRequest.php`: `@return array<string,mixed>` rules; validates `proposal_kind` in ModificationProposalKind values; `changes` array required, each item: `change_kind` in ['add','remove','update'], `target_item_public_id` string nullable (required for update/remove), `payload` array (required for add/update); `vendor_explanation` nullable with keys 'en','ar'
- [X] T036 [US3] Add modify endpoint to `Vendor/BookingController`: `modify(VendorModifyRequest $request, string $bookingVendorPublicId)` — 3-line body; also add `modifications(string $bookingVendorPublicId)` to list vendor's modifications
- [X] T037 [US3] Add vendor routes to `app/Modules/Booking/Routes/vendor.php`: `POST booking-vendors/{bv_public_id}/modify` → `Vendor/BookingController@modify`; `GET booking-vendors/{bv_public_id}/modifications` → `Vendor/BookingController@modifications`
- [X] T038 [US3] Register in `BookingServiceProvider`: `VendorModificationProposed` → `WriteNegotiationSnapshotListener`; `VendorModificationProposed` → `WriteBookingStateTransitionListener`
- [X] T039 [US3] Write Pest feature test `tests/Feature/Modules/Booking/VendorModifyTest.php`: group `['booking','negotiation']`; cover: change_price modification → diff_snapshot has correct before/after; change_slot on Rental item → diff_snapshot captures effective_starts_at/ends_at; add_item modification → booking_modification_items row with change_kind=add and target_booking_item_id=NULL; remove_item → booking_modification_items with change_kind=remove; booking moves to customer_review after modify; booking_vendor.sub_status=modified; duplicate pending modification → 409; vendor modifies wrong booking_vendor → 403; modify non-pending booking_vendor → 409

**Checkpoint**: `./vendor/bin/pest tests/Feature/Modules/Booking/VendorModifyTest.php` — all assertions pass.

---

## Phase 6: User Story 4 — Customer Reviews and Decides (P3)

**Goal**: Customer lists pending modifications with diff. Customer accepts (changes applied, totals recalculated, booking advances) or rejects (items unchanged, loop continues). Idempotency on accept.

**Independent Test**: `./vendor/bin/pest tests/Feature/Modules/Booking/CustomerDecisionTest.php` — quickstart.md Scenarios 2–3 complete.

- [X] T040 [P] [US4] Create `CustomerModificationDecisionDTO` in `app/Modules/Booking/Application/DTOs/CustomerModificationDecisionDTO.php`: `readonly` class, constructor `(public int $bookingId, public int $customerId, public string $modificationPublicId, public string $decision, public string $idempotencyKey)`; `$decision` is 'accepted' or 'rejected'
- [X] T041 [US4] Create `CustomerConfirmModifiedBookingAction` in `app/Modules/Booking/Application/Actions/CustomerConfirmModifiedBookingAction.php`: `execute(CustomerModificationDecisionDTO $dto)` returns `Booking`; check idempotency first; inside `DB::transaction`: load modification by public_id scoped to booking, validate status=pending and booking belongs to customer; if `$dto->decision === 'accepted'`: iterate `booking_modification_items` applying each change (update booking_item columns, insert new booking_item for add, delete booking_item for remove); set modification.status='customer_accepted', customer_decision_at=now(); if all booking_vendors accepted → set booking confirmed; else set booking back to vendor_review checking remaining vendors; if `$dto->decision === 'rejected'`: set modification.status='customer_rejected', customer_decision_at=now(); reset booking_vendor.sub_status='pending'; set booking.lifecycle_status='vendor_review'; append state_transitions; `DB::afterCommit` fires `CustomerModificationDecided($modification, $dto->decision)` and possibly `BookingConfirmed($booking)` or `VendorAccepted` equivalent
- [X] T042 [P] [US4] Create `CustomerModificationDecisionRequest` in `app/Modules/Booking/Http/Requests/CustomerModificationDecisionRequest.php`: validates `decision` in `['accepted','rejected']`; requires Idempotency-Key header
- [X] T043 [US4] Add decide and list endpoints to `BookingNegotiationController`: `listModifications(string $bookingPublicId)` — returns `BookingModificationResource::collection`; `decideModification(CustomerModificationDecisionRequest $request, string $bookingPublicId, string $modificationPublicId)` — 3-line body calling CustomerConfirmModifiedBookingAction
- [X] T044 [US4] Add customer routes to `app/Modules/Booking/Routes/customer.php`: `GET bookings/{booking_public_id}/modifications` → `BookingNegotiationController@listModifications`; `POST bookings/{booking_public_id}/modifications/{modification_public_id}/decide` → `BookingNegotiationController@decideModification`
- [X] T045 [US4] Register in `BookingServiceProvider`: `CustomerModificationDecided` → `WriteNegotiationSnapshotListener`, `WriteBookingStateTransitionListener`, `RecalculateBookingTotalsListener`
- [X] T046 [US4] Write Pest feature test `tests/Feature/Modules/Booking/CustomerDecisionTest.php`: group `['booking','negotiation']`; cover: accept modification → booking_item.unit_price_minor updated → totals recalculated → booking confirmed (Scenario 2 full); accept add-item modification → new booking_items row created; accept remove-item modification → booking_item deleted; reject modification → booking_item unchanged → booking returns to vendor_review → booking_vendor.sub_status=pending; idempotency on accept (duplicate key → same response, no duplicate application); decide on non-pending modification → 409; decide on other customer's booking → 403; unauthenticated → 401

**Checkpoint**: `./vendor/bin/pest tests/Feature/Modules/Booking/CustomerDecisionTest.php` — all assertions pass. Run quickstart.md Scenario 3 manually.

---

## Phase 7: User Story 5 — Vendor Rejects (P3)

**Goal**: Vendor rejects their portion. Booking moves to customer_review. When ALL vendors reject, booking cancelled and inventory released. No auto-replacement.

**Independent Test**: `./vendor/bin/pest tests/Feature/Modules/Booking/VendorRejectTest.php` — quickstart.md Scenario 5.

- [X] T047 [US5] Create `VendorRejectBookingAction` in `app/Modules/Booking/Application/Actions/VendorRejectBookingAction.php`: `execute(int $bookingVendorId, int $vendorProfileId, ?array $rejectionReason)` returns `BookingVendor`; inside `DB::transaction`: load BookingVendor with lock, validate sub_status=pending and belongs to vendor; set sub_status='rejected', responded_at=now(); if reason provided: store in `booking_vendor.rejection_reason` (existing JSON column); set booking.lifecycle_status='customer_review'; check if ALL booking_vendors rejected → if so: set booking.lifecycle_status='cancelled', cancelled_at=now(); append state_transitions; `DB::afterCommit` fires `VendorRejected($bookingVendor)` and optionally `BookingCancelled($booking)` if all rejected
- [X] T048 [P] [US5] Create `VendorRejectRequest` in `app/Modules/Booking/Http/Requests/VendorRejectRequest.php`: `rejection_reason` nullable array with 'en','ar' keys each max 500 chars
- [X] T049 [US5] Add reject endpoint to `Vendor/BookingController`: `reject(VendorRejectRequest $request, string $bookingVendorPublicId)` — 3-line body
- [X] T050 [US5] Add vendor route to `app/Modules/Booking/Routes/vendor.php`: `POST booking-vendors/{bv_public_id}/reject` → `Vendor/BookingController@reject`
- [X] T051 [US5] Create `ReleaseInventoryOnCancellationListener` in `app/Modules/Booking/Application/Listeners/ReleaseInventoryOnCancellationListener.php`: `handle(BookingCancelled $event)` — `DB::table('service_inventory_reservations')` updates `held` reservations for this booking's Rental items to `status='released'`, `released_at=now()`, `release_reason='booking_cancelled'`
- [X] T052 [US5] Register in `BookingServiceProvider`: `VendorRejected` → `WriteNegotiationSnapshotListener`, `WriteBookingStateTransitionListener`; `BookingCancelled` → `WriteNegotiationSnapshotListener`, `WriteBookingStateTransitionListener`, `ReleaseInventoryOnCancellationListener`
- [X] T053 [US5] Write Pest feature test `tests/Feature/Modules/Booking/VendorRejectTest.php`: group `['booking','negotiation']`; cover: single vendor rejects → booking moves to customer_review → no auto-replacement; all vendors reject → booking.lifecycle_status='cancelled'; Rental inventory reservation released after cancellation; vendor rejects wrong booking_vendor → 403; reject non-pending booking_vendor → 409; per-type: Rental reservation released on cancel, Sale/Digital no reservation side effects

**Checkpoint**: `./vendor/bin/pest tests/Feature/Modules/Booking/VendorRejectTest.php` — all assertions pass.

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Admin Filament monitor, final wiring, PHPStan compliance.

- [X] T054 Create read-only `BookingsMonitorResource` in `app/Modules/Booking/Filament/Resources/BookingsMonitorResource.php`: list page only (no create/edit/delete actions); default query: `lifecycle_status IN ('vendor_review','customer_review')`; columns: reference_no, customer name (via DB join), product_types (formatted count per type from booking_items), total_minor money, submitted_at dateTime, nearest response_deadline; filters: SelectFilter on lifecycle_status, SelectFilter on product_type (join booking_items); navigation group 'Bookings'; navigation label 'Negotiation Monitor'
- [X] T055 Add admin routes to `app/Modules/Booking/Routes/admin.php` for `BookingsMonitorResource` panel registration (Filament auto-discovers from Modules path — verify `AdminPanelProvider` glob covers this)
- [X] T056 [P] Write `NegotiationLoopTest` in `tests/Feature/Modules/Booking/NegotiationLoopTest.php`: group `['booking','negotiation']`; full loop Scenario 3 (submit → vendor modifies → customer rejects → vendor accepts → confirmed); full loop Scenario 4 (multi-vendor partial acceptance); all three product types in a single booking going through full negotiation to confirm; loop counter: 2 rounds of modification-rejection before acceptance
- [X] T057 [P] Run PHPStan on all new Booking module files: `./vendor/bin/phpstan analyse app/Modules/Booking` — resolve any `missingType.iterableValue` or `argument.type` errors; add `@return array<string,mixed>` to all `toArray()` and `rules()` methods; add `@property` docblocks to new models if needed
- [X] T058 Run full test suite: `./vendor/bin/pest tests/Feature/Modules/Booking/ --no-progress` — all 5 test files pass; confirm quickstart.md Scenarios 1–6 covered by tests

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: No dependencies — start immediately. T001–T003 fully parallel; T004–T005 fully parallel with T001–T003.
- **Phase 2 (Foundational)**: Depends on Phase 1 completion. T006 depends on T001–T003 (enum casts). T007 depends on T003. T008–T014 are independent domain events (all parallel). T015 depends on T006 (model). T018 should run last in Phase 2.
- **Phase 3 (US1)**: Depends on Phase 2. T020 depends on T019 and T008 (event). T022 depends on T020, T021. T023 depends on T022. T024, T025, T026 can proceed in sequence after T022.
- **Phase 4 (US2)**: Depends on Phase 3 (submit must work first). T027 depends on T009, T013. T028 depends on T027. T030 depends on T013.
- **Phase 5 (US3)**: Depends on Phase 3 (must have submitted booking to modify). T034 depends on T002 (ProposalKind enum). T036 depends on T033. T035 depends on T034, T033, T006, T007.
- **Phase 6 (US4)**: Depends on Phase 5 (must have a pending modification to decide on). T041 depends on T040, T006, T007.
- **Phase 7 (US5)**: Depends on Phase 3 (must have submitted booking to reject). Independent of US3/US4. T047 depends on T011, T014.
- **Phase 8 (Polish)**: Depends on all user story phases.

### User Story Dependencies

- **US1 (P1)**: Foundational → US1 (no other story dependency)
- **US2 (P2)**: US1 must be complete (vendor can only accept a submitted booking)
- **US3 (P2)**: US1 must be complete (vendor can only modify a submitted booking)
- **US4 (P3)**: US3 must be complete (customer can only decide on a pending modification)
- **US5 (P3)**: US1 must be complete (vendor can only reject a submitted booking); independent of US2/US3/US4

### Parallel Opportunities Within Phases

- **Phase 1**: T001, T002, T003, T004, T005 all fully parallel
- **Phase 2**: T006, T007, T008–T014, T015, T016, T017 all parallel; T018 after all
- **Phase 3**: T019, T021, T024 parallel; T020 after T019; T022 after T020+T021
- **Phase 4 + Phase 5**: US2 and US3 can be developed in parallel (different files, independent test scenarios)
- **Phase 6 + Phase 7**: US4 depends on US3 being done; US5 is fully independent of US3/US4 and can run with US3 in parallel

---

## Parallel Example: Phase 2

```text
Launch all together (no dependencies between them):
- T006: BookingModification model
- T007: BookingModificationItem model
- T008: BookingSubmittedToVendor event
- T009: VendorAccepted event
- T010: VendorModificationProposed event
- T011: VendorRejected event
- T012: CustomerModificationDecided event
- T013: BookingConfirmed event
- T014: BookingCancelled event
- T015: BookingModificationResource
- T016: vendor.php routes scaffold
- T017: admin.php routes scaffold
Then:
- T018: BookingServiceProvider update (after all above)
```

## Parallel Example: Phase 5 + Phase 7 (run concurrently)

```text
After Phase 3 (US1) complete:
- Parallel track A: T033–T039 (US3 vendor modify)
- Parallel track B: T047–T052 (US5 vendor reject)
Then Phase 6 (US4) after Phase 5 (US3) complete.
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1 + Phase 2 (enums, migrations, models, events, infrastructure)
2. Complete Phase 3 (US1: submit booking)
3. **STOP and validate**: `./vendor/bin/pest tests/Feature/Modules/Booking/SubmitBookingTest.php`
4. Customer can now submit a booking → vendor receives it → negotiation begins

### Incremental Delivery

1. Phase 1 + 2 → Foundation ready
2. Phase 3 (US1) → Customer can submit ✓
3. Phase 4 (US2) → Vendor can accept → booking confirms ✓ (happy path works end-to-end)
4. Phase 5 (US3) + Phase 7 (US5) in parallel → Vendor can modify or reject ✓
5. Phase 6 (US4) → Customer can decide on modifications ✓ (full loop works)
6. Phase 8 → Monitor + polish ✓

---

## Notes

- `[P]` tasks = different files, no dependencies on incomplete tasks in same phase
- Verify `php artisan migrate` succeeds before implementing models (Phase 1 checkpoint)
- All new controllers: 3-line body max (CLAUDE.md rule)
- All new Actions: single `execute()` method, wrap mutations in `DB::transaction`, fire events via `DB::afterCommit`
- No cross-module Eloquent imports: use `DB::table('service_inventory_reservations')` for inventory operations
- Mark each task `[X]` immediately upon completion
- `booking_modification_items` is append-only: no `updated_at` in migration, no `$timestamps` in model (only created_at)
- PHPStan level 8: add `@return array<string,mixed>` to all `rules()` and `toArray()` methods
