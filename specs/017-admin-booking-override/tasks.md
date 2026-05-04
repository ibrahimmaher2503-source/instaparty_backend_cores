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
---

# Tasks: Admin Booking Override

**Phase**: 6.5 (Week 7, 2 days) | **PRD**: FR-16, FR-17, FR-18
**Input**: `specs/017-admin-booking-override/` (spec.md, plan.md, research.md, data-model.md, contracts/)
**ADR Required**: `docs/adr/0013-admin-booking-override.md` — must be Accepted before any migration runs

> **Day 1**: Phases 1–2 (ADR + Foundation) + Phases 3–6 Implementation sections
> **Day 2**: Phases 3–6 Test sections + Phase 7 (Polish)

---

## Format: `[ID] [P?] [Story?] Description`

- **[P]**: Parallelizable — different files, no dependencies on incomplete tasks
- **[Story]**: Maps to user story (US1–US4 from spec.md)
- Tests are included — explicitly required by spec Day 2 checklist

---

## Phase 1: Setup — ADR & Permissions

**Purpose**: Must be complete before any code. ADR gates the migration run.

- [ ] T001 Write and mark Accepted `docs/adr/0013-admin-booking-override.md` covering: new `booking_admin_interventions` table (Phase 6.5 addition to 60-table schema), `customer_consent_status` as only mutable column, cross-module refund via `BookingForceCancelled` event, `VendorSubStatus::TimedOut`, FR-17 enforcement mechanism
- [ ] T002 [P] Add 4 new Spatie permission slugs to `database/seeders/RolesAndPermissionsSeeder.php` (or equivalent): `force_cancel_booking`, `timeout_vendor_response`, `propose_alternative_vendor`, `add_booking_note` — assign to `super_admin` and `booking_manager` roles

---

## Phase 2: Foundation — Schema, Enums, Model, Events

**Purpose**: Shared infrastructure used by all four user stories. Must complete before any story implementation.

⚠️ **CRITICAL**: Run `php artisan migrate` after T003 and T004. Run `php artisan shield:generate --all` after T002.

- [ ] T003 Write migration `app/Modules/Booking/Database/Migrations/2026_05_03_000012_create_booking_admin_interventions_table.php` — columns: `id` BIGINT PK, `public_id` CHAR(26) UNIQUE, `booking_id` FK→bookings.id restrictOnDelete, `admin_id` FK→users.id restrictOnDelete, `intervention_type` ENUM('force_cancel','vendor_timeout','vendor_proposal','admin_note'), `reason` TEXT NOT NULL, `before_state` JSON NOT NULL, `after_state` JSON NOT NULL, `customer_consent_status` ENUM('pending','accepted','rejected','consent_expired') NULL, `proposed_vendor_id` FK→vendor_profiles.id NULL restrictOnDelete, `consent_expires_at` TIMESTAMP NULL, `created_at` TIMESTAMP, `updated_at` TIMESTAMP; indexes: `(booking_id, intervention_type)`, `(customer_consent_status, consent_expires_at)`, `(admin_id, created_at)`. No softDeletes. Charset utf8mb4 unicode_ci.
- [ ] T004 Write migration `app/Modules/Booking/Database/Migrations/2026_05_03_000013_add_timed_out_to_booking_vendors_sub_status.php` — alters `booking_vendors.sub_status` ENUM to add `'timed_out'` value
- [ ] T005 [P] Create enum `app/Modules/Booking/Domain/Enums/InterventionType.php` — cases: `ForceCanel = 'force_cancel'`, `VendorTimeout = 'vendor_timeout'`, `VendorProposal = 'vendor_proposal'`, `AdminNote = 'admin_note'`
- [ ] T006 [P] Create enum `app/Modules/Booking/Domain/Enums/CustomerConsentStatus.php` — cases: `Pending = 'pending'`, `Accepted = 'accepted'`, `Rejected = 'rejected'`, `ConsentExpired = 'consent_expired'`
- [ ] T007 [P] Extend `app/Modules/Booking/Domain/Enums/VendorSubStatus.php` — add `TimedOut = 'timed_out'` case
- [ ] T008 Create model `app/Modules/Booking/Domain/Models/BookingAdminIntervention.php` — fillable columns, casts (`before_state → array`, `after_state → array`, `intervention_type → InterventionType::class`, `customer_consent_status → CustomerConsentStatus::class`, `consent_expires_at → datetime`), relationships: `belongsTo(Booking::class)`, `belongsTo(User::class, 'admin_id')`, `belongsTo(VendorProfile::class, 'proposed_vendor_id')`. No SoftDeletes. No timestamps trait override (both created_at and updated_at present).
- [ ] T009 [P] Create DTO `app/Modules/Booking/Application/DTOs/AdminInterventionDTO.php` — public readonly properties: `int $bookingId`, `int $adminId`, `InterventionType $interventionType`, `string $reason`, `?int $proposedVendorId`, `?Carbon $consentExpiresAt`
- [ ] T010 [P] Create DTO `app/Modules/Booking/Application/DTOs/VendorProposalResponseDTO.php` — public readonly properties: `string $interventionPublicId`, `int $customerId`, `CustomerConsentStatus $decision`
- [ ] T011 [P] Create domain event `app/Modules/Booking/Domain/Events/BookingForceCancelled.php` — constructor: `public Booking $booking`, `public BookingAdminIntervention $intervention`
- [ ] T012 [P] Create domain event `app/Modules/Booking/Domain/Events/VendorResponseTimedOut.php` — constructor: `public Booking $booking`, `public BookingAdminIntervention $intervention`
- [ ] T013 [P] Create domain event `app/Modules/Booking/Domain/Events/AlternativeVendorProposed.php` — constructor: `public Booking $booking`, `public BookingAdminIntervention $intervention`
- [ ] T014 [P] Create domain event `app/Modules/Booking/Domain/Events/VendorProposalDecided.php` — constructor: `public Booking $booking`, `public BookingAdminIntervention $intervention`, `public bool $accepted`
- [ ] T015 Update `app/Modules/Booking/Providers/BookingServiceProvider.php` — register all 4 new events with their listeners (T020+T021, T032+T033, T049+T050, T060); register `ExpireVendorProposalsCommand` in `$commands`; add scheduler entry in `boot()` calling the command hourly

**Checkpoint**: Run `php artisan migrate` + `php artisan shield:generate --all` — all green before story implementation begins.

---

## Phase 3: User Story 1 — Admin Force-Cancels a Stuck Booking (P1) 🎯 MVP

**Goal**: Admin can cancel any non-completed booking with a mandatory reason, triggering per-type refund and triple-logging the intervention.

**Independent Test**: Create booking in `vendor_review` state → POST force-cancel → assert `lifecycle_status = 'cancelled'`, intervention row created, `booking_state_transitions` row appended, `audit_logs` row appended, refund initiated for correct product type.

### Tests for User Story 1

- [ ] T016 [P] [US1] Write Pest Feature test `tests/Feature/Modules/Booking/AdminInterventionForceCancelTest.php` — happy path: admin POSTs force-cancel on `vendor_review` booking → 200, `lifecycle_status = 'cancelled'`, `booking_admin_interventions` row with `intervention_type = 'force_cancel'`, `booking_state_transitions` row with `trigger_kind = 'admin'`, `audit_logs` row; assert `BookingForceCancelled` event dispatched
- [ ] T017 [P] [US1] Pest — refund per product type (3 cases in same file): rental (refund allowed when > 24h before event), sale (refund allowed when item_status = 'pending'), digital (respects `is_refundable_after_delivery` flag); use `Event::fake()` to assert `BookingForceCancelled` dispatched with correct booking
- [ ] T018 [P] [US1] Pest — validation: 422 when `reason` is empty; 422 when booking is already `completed`; 401 unauthenticated; 403 when caller lacks `force_cancel_booking` permission

### Implementation for User Story 1

- [ ] T019 [P] [US1] Create FormRequest `app/Modules/Booking/Http/Requests/ForceCancelBookingRequest.php` — validates: `reason` required string min:10; authorization gate: `force_cancel_booking` permission
- [ ] T020 [US1] Create action `app/Modules/Booking/Application/Actions/ForceCancelBookingAction.php` — `execute(Booking $booking, int $adminUserId, string $reason): BookingAdminIntervention`. DB::transaction: (1) guard booking not `completed`; (2) snapshot before_state; (3) update `booking.lifecycle_status = 'cancelled'`; (4) update all `booking_vendors.sub_status = 'cancelled'`; (5) `BookingAdminIntervention::create(...)` with `intervention_type = 'force_cancel'`, after_state; (6) `BookingStateTransition::create(...)` with `trigger_kind = 'admin'`; (7) `DB::table('audit_logs')->insert(...)`. DB::afterCommit: `BookingForceCancelled::dispatch($booking, $intervention)`.
- [ ] T021 [US1] Create Payments listener `app/Modules/Payments/Application/Listeners/OnBookingForceCancelledRefundListener.php` — implements `ShouldQueue`; `handle(BookingForceCancelled $event)`: iterates `$event->booking->items`, calls `RefundPolicyService->policyFor($item->product_type, $item->item_status, $booking->event_starts_at, $item->service_id)`, if `$policy->isAllowed()` calls `InitiateRefundAction->execute(...)` per item
- [ ] T022 [US1] Create Communication listener `app/Modules/Communication/Application/Listeners/OnBookingForceCancelled.php` — implements `ShouldQueue`; calls `DispatchNotificationAction->execute(DispatchNotificationDTO::forCustomer($booking->customer_id, 'booking.admin_force_cancelled', ['booking_public_id' => $booking->public_id]))`
- [ ] T023 [P] [US1] Create API Resource `app/Modules/Booking/Http/Resources/BookingAdminInterventionResource.php` — returns: `public_id`, `intervention_type` (translated label via `app()->getLocale()`), `reason`, `before_state`, `after_state`, `customer_consent_status` (translated, nullable), `created_at` (UTC ISO 8601)
- [ ] T024 [US1] Create controller `app/Modules/Booking/Http/Controllers/Admin/BookingInterventionController.php` — `forceCancelAction(ForceCancelBookingRequest $request, string $bookingPublicId)` — 3-line body: resolve booking by public_id, call `ForceCancelBookingAction->execute(...)`, return `ApiResponse::success(new BookingAdminInterventionResource($intervention))`
- [ ] T025 [US1] Add route to `app/Modules/Booking/Routes/admin.php` — `Route::post('bookings/{bookingPublicId}/force-cancel', [BookingInterventionController::class, 'forceCancelAction'])->middleware(['auth:sanctum', 'role:super_admin|booking_manager'])`
- [ ] T026 [US1] Extend Filament `app/Modules/Booking/Filament/Resources/BookingResource.php` — add `Action::make('forceCancelBooking')` in `->actions([...])` with modal form containing `Textarea::make('reason')->required()->minLength(10)`; delegates to `ForceCancelBookingAction::execute()`; gated by `visible(fn() => auth()->user()->can('force_cancel_booking'))` and `visible(fn(Booking $record) => $record->lifecycle_status !== LifecycleStatus::Completed)`

**Checkpoint**: POST `/api/v1/admin/bookings/{id}/force-cancel` fully functional. All T016–T018 tests green.

---

## Phase 4: User Story 2 — Admin Forces Vendor Response Timeout (P2)

**Goal**: Admin manually moves a `vendor_review` booking to `cancelled` when the vendor is unresponsive, marking the vendor's sub-status as `timed_out`.

**Independent Test**: Create booking in `vendor_review` state → POST timeout-vendor → assert `lifecycle_status = 'cancelled'`, `booking_vendors.sub_status = 'timed_out'` for the vendor, intervention row, state transition row, audit row, `VendorResponseTimedOut` event dispatched.

### Tests for User Story 2

- [ ] T027 [P] [US2] Write Pest Feature test `tests/Feature/Modules/Booking/AdminInterventionVendorTimeoutTest.php` — happy path: `vendor_review` booking → timeout action → 200, `lifecycle_status = 'cancelled'`, `booking_vendors.sub_status = 'timed_out'`, triple-logged, `VendorResponseTimedOut` event dispatched
- [ ] T028 [P] [US2] Pest — validation: 422 when booking not in `vendor_review` state; 422 when `reason` empty; 401 unauthenticated; 403 wrong role

### Implementation for User Story 2

- [ ] T029 [P] [US2] Create FormRequest `app/Modules/Booking/Http/Requests/TimeoutVendorResponseRequest.php` — validates `reason` required string min:10; gate `timeout_vendor_response` permission
- [ ] T030 [US2] Create action `app/Modules/Booking/Application/Actions/TimeoutVendorResponseAction.php` — `execute(Booking $booking, int $adminUserId, string $reason): BookingAdminIntervention`. Guard `$booking->lifecycle_status === LifecycleStatus::VendorReview` else throw `InvalidArgumentException`. DB::transaction: snapshot before_state; update `booking.lifecycle_status = 'cancelled'`; update matching `booking_vendors.sub_status = VendorSubStatus::TimedOut`; insert `booking_admin_interventions` (`intervention_type = 'vendor_timeout'`); append `booking_state_transitions` (`trigger_kind = 'admin'`); insert `audit_logs`. DB::afterCommit: `VendorResponseTimedOut::dispatch($booking, $intervention)`.
- [ ] T031 [US2] Create Communication listener `app/Modules/Communication/Application/Listeners/OnVendorResponseTimedOut.php` — implements `ShouldQueue`; dispatches `booking.vendor_timed_out` notification to customer
- [ ] T032 [US2] Add `timeoutVendorAction(TimeoutVendorResponseRequest $request, string $bookingPublicId)` method to `app/Modules/Booking/Http/Controllers/Admin/BookingInterventionController.php` — 3-line body
- [ ] T033 [US2] Add route to `app/Modules/Booking/Routes/admin.php` — `Route::post('bookings/{bookingPublicId}/timeout-vendor', [BookingInterventionController::class, 'timeoutVendorAction'])`
- [ ] T034 [US2] Add `Action::make('timeoutVendorResponse')` to `BookingResource.php` Filament actions — gated by `timeout_vendor_response` permission AND `visible(fn(Booking $r) => $r->lifecycle_status === LifecycleStatus::VendorReview)`

**Checkpoint**: POST `/api/v1/admin/bookings/{id}/timeout-vendor` functional. All T027–T028 tests green.

---

## Phase 5: User Story 3 — Admin Proposes Alternative Vendor to Customer (P3)

**Goal**: Admin proposes a replacement vendor; customer accepts/rejects via API. Booking is never auto-switched (FR-17 hard guard). Pending proposals expire after 48h via scheduled command.

**Independent Test**: POST propose-vendor → intervention with `customer_consent_status = 'pending'` created, booking state unchanged. Customer PATCH respond/accepted → `customer_consent_status = 'accepted'`. Assert no auto vendor replacement at any point.

### Tests for User Story 3

- [ ] T035 [P] [US3] Write Pest Feature test `tests/Feature/Modules/Booking/AdminInterventionVendorProposalTest.php` — (a) happy path proposal: intervention created with `customer_consent_status = 'pending'`, booking lifecycle_status UNCHANGED, `AlternativeVendorProposed` event dispatched; (b) 422 when pending proposal already exists; (c) 422 when proposed vendor not approved for booking's product type
- [ ] T036 [P] [US3] Pest — FR-17 enforcement: POST propose-vendor does NOT change `booking_vendors` records; original vendor remains intact; assert `booking_vendors` count unchanged after proposal
- [ ] T037 [P] [US3] Pest — customer consent flow: PATCH respond `decision=accepted` → `customer_consent_status = 'accepted'`, `VendorProposalDecided` event dispatched with `$accepted = true`; PATCH respond `decision=rejected` → `customer_consent_status = 'rejected'`; 422 on already-decided proposal; 404 when intervention does not belong to customer's booking
- [ ] T038 [P] [US3] Pest — proposal expiry: run `ExpireVendorProposalsCommand` after setting `consent_expires_at` in the past → `customer_consent_status = 'consent_expired'`

### Implementation for User Story 3

- [ ] T039 [P] [US3] Create FormRequest `app/Modules/Booking/Http/Requests/ProposeAlternativeVendorRequest.php` — validates: `proposed_vendor_public_id` required exists in vendor_profiles; `reason` required string min:10; `consent_ttl_hours` optional integer min:1 max:168 default:48; gate `propose_alternative_vendor` permission
- [ ] T040 [P] [US3] Create FormRequest `app/Modules/Booking/Http/Requests/RespondToVendorProposalRequest.php` — validates: `decision` required in `['accepted', 'rejected']`; authorization: customer owns the booking
- [ ] T041 [US3] Create action `app/Modules/Booking/Application/Actions/ProposeAlternativeVendorAction.php` — `execute(Booking $booking, int $adminUserId, int $proposedVendorId, string $reason, int $consentTtlHours = 48): BookingAdminIntervention`. Guards: (1) no existing `pending` intervention of type `vendor_proposal` for this booking; (2) proposed vendor has `vendor_approved_product_types` entry for each product type present in `booking_items`. DB::transaction: snapshot before_state = after_state (no state change); insert `booking_admin_interventions` with `intervention_type = 'vendor_proposal'`, `customer_consent_status = 'pending'`, `proposed_vendor_id`, `consent_expires_at = now() + $consentTtlHours hours`; insert `audit_logs`. DB::afterCommit: `AlternativeVendorProposed::dispatch($booking, $intervention)`.
- [ ] T042 [US3] Create action `app/Modules/Booking/Application/Actions/RespondToVendorProposalAction.php` — `execute(VendorProposalResponseDTO $dto): BookingAdminIntervention`. Guards: (1) intervention exists, is `vendor_proposal` type, belongs to customer's booking; (2) `customer_consent_status === 'pending'`. DB::transaction: update `intervention.customer_consent_status` to accepted/rejected; if accepted: also append `booking_state_transitions` row and insert `audit_logs`; DB::afterCommit: `VendorProposalDecided::dispatch($booking, $intervention, $dto->decision === CustomerConsentStatus::Accepted)`.
- [ ] T043 [US3] Create Communication listener `app/Modules/Communication/Application/Listeners/OnAlternativeVendorProposed.php` — implements `ShouldQueue`; dispatches `booking.vendor_proposal_received` to customer with proposal public_id in context
- [ ] T044 [US3] Create Communication listener `app/Modules/Communication/Application/Listeners/OnVendorProposalDecided.php` — implements `ShouldQueue`; dispatches appropriate notification based on `$event->accepted` (confirmation or acknowledgment to customer)
- [ ] T045 [P] [US3] Create API Resource `app/Modules/Booking/Http/Resources/VendorProposalResource.php` — customer-facing: `public_id`, `proposed_vendor_name` (locale-converted), `customer_consent_status` (label), `consent_expires_at` (UTC ISO 8601). No `reason` or admin identity exposed.
- [ ] T046 [US3] Add `proposeVendorAction(ProposeAlternativeVendorRequest $request, string $bookingPublicId)` method to `BookingInterventionController.php` — 3-line body: resolve booking, call `ProposeAlternativeVendorAction->execute(...)`, return 201 with `BookingAdminInterventionResource`
- [ ] T047 [US3] Create customer controller `app/Modules/Booking/Http/Controllers/Customer/VendorProposalResponseController.php` — `respond(RespondToVendorProposalRequest $request, string $bookingPublicId, string $interventionPublicId)` — 3-line body
- [ ] T048 [US3] Add routes to `app/Modules/Booking/Routes/admin.php` and `customer.php` — `Route::post('bookings/{bookingPublicId}/propose-vendor', ...)` in admin; `Route::patch('bookings/{bookingPublicId}/vendor-proposals/{interventionPublicId}/respond', ...)` in customer
- [ ] T049 [US3] Add `Action::make('proposeAlternativeVendor')` to `BookingResource.php` Filament — gated by `propose_alternative_vendor` permission; form: `Select::make('proposed_vendor_public_id')` (searchable vendor list filtered by product type approval), `Textarea::make('reason')->required()`, `TextInput::make('consent_ttl_hours')->numeric()->default(48)`
- [ ] T050 [US3] Create console command `app/Modules/Booking/Console/Commands/ExpireVendorProposalsCommand.php` — signature `booking:expire-vendor-proposals`; queries `booking_admin_interventions` where `intervention_type = 'vendor_proposal'` AND `customer_consent_status = 'pending'` AND `consent_expires_at < now()`; updates `customer_consent_status = 'consent_expired'`; logs count expired; register in `BookingServiceProvider` scheduler `->hourly()`

**Checkpoint**: Both proposal and consent endpoints functional. FR-17 test (T036) green. Expiry command runs without error.

---

## Phase 6: User Story 4 — Admin Adds Internal Note (P4)

**Goal**: Admin appends a plain-text note to any booking for internal tracking without mutating any booking state.

**Independent Test**: POST note → `booking_admin_interventions` row with `intervention_type = 'admin_note'`, `before_state === after_state`, booking statuses unchanged, `audit_logs` row appended.

### Tests for User Story 4

- [ ] T051 [P] [US4] Write Pest Feature test `tests/Feature/Modules/Booking/AdminInterventionNoteTest.php` — happy path: POST note → 201, intervention row with `intervention_type = 'admin_note'`, booking `lifecycle_status` unchanged, `audit_logs` row; no `booking_state_transitions` row created; note body stored in `reason` column
- [ ] T052 [P] [US4] Pest — validation: 422 on empty body; 403 lacks `add_booking_note` permission; 401 unauthenticated

### Implementation for User Story 4

- [ ] T053 [P] [US4] Create FormRequest `app/Modules/Booking/Http/Requests/AddAdminNoteRequest.php` — validates `reason` required string min:5; gate `add_booking_note` permission
- [ ] T054 [US4] Create action `app/Modules/Booking/Application/Actions/AddAdminNoteAction.php` — `execute(Booking $booking, int $adminUserId, string $reason): BookingAdminIntervention`. DB::transaction: snapshot before_state; insert `booking_admin_interventions` with `intervention_type = 'admin_note'`, `before_state === after_state` (no transition), `customer_consent_status = null`; insert `audit_logs`. No `booking_state_transitions` row. No domain event (no side effects needed for notes).
- [ ] T055 [US4] Add `addNoteAction(AddAdminNoteRequest $request, string $bookingPublicId)` method to `BookingInterventionController.php` — 3-line body
- [ ] T056 [US4] Add route to `app/Modules/Booking/Routes/admin.php` — `Route::post('bookings/{bookingPublicId}/notes', [BookingInterventionController::class, 'addNoteAction'])`
- [ ] T057 [US4] Add `Action::make('addAdminNote')` to `BookingResource.php` Filament — gated by `add_booking_note` permission; always visible for any booking state; form: `Textarea::make('reason')->required()->label(__('booking.admin_note_label'))`

**Checkpoint**: All 4 intervention types functional. POST `/admin/bookings/{id}/notes` green. T051–T052 tests green.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: List endpoint, Filament detail view, translations, shield generate, api-registry, architecture test verification.

- [ ] T058 [P] Add `listInterventionsAction(string $bookingPublicId)` to `BookingInterventionController.php` — returns cursor-paginated `BookingAdminInterventionResource` collection ordered by `created_at DESC`; add route `Route::get('bookings/{bookingPublicId}/interventions', ...)`
- [ ] T059 Create `app/Modules/Booking/Filament/Resources/BookingResource/Pages/ViewBooking.php` — extends `ViewRecord`; infolist shows booking summary + embedded `RelationManager`-style `RepeatableEntry` for `bookingAdminInterventions` relationship; all 4 intervention `Action` buttons shown in page header; add `ViewAction::make()` to `BookingResource::getPages()` array
- [ ] T060 [P] Add 4 notification template seeder entries to `app/Modules/Communication/Database/Seeders/NotificationTemplateSeeder.php` (or equivalent) for event keys: `booking.admin_force_cancelled`, `booking.vendor_timed_out`, `booking.vendor_proposal_received`, `booking.vendor_proposal_expiry_reminder` — each with EN+AR `subject` and `body` JSON columns, both `email` and `push` channel variants
- [ ] T061 [P] Add intervention labels to `app/Modules/Booking/Resources/lang/en/booking.php` — keys: `intervention_type.*` (force_cancel, vendor_timeout, vendor_proposal, admin_note), `customer_consent_status.*` (pending, accepted, rejected, consent_expired), `admin_note_label`, Filament button labels
- [ ] T062 [P] Add Arabic translations to `app/Modules/Booking/Resources/lang/ar/booking.php` — mirror all keys from T061 with Arabic text
- [ ] T063 Run `php artisan shield:generate --all` and verify the 4 new permission slugs (force_cancel_booking, timeout_vendor_response, propose_alternative_vendor, add_booking_note) appear in `filament-shield` policy files
- [ ] T064 Update `app/Modules/Booking/Providers/BookingServiceProvider.php` — ensure all 4 events are registered with their listeners (Booking + Communication + Payments listeners per event); ensure `ExpireVendorProposalsCommand` is scheduled hourly
- [ ] T065 Update `.specify/memory/api-registry.md` — add 6 new endpoint rows per `specs/017-admin-booking-override/plan.md` API Registry Updates section
- [ ] T066 [P] Verify architecture tests in `tests/Architecture/` pass: `NoCrossModuleModelImportsTest` (ForceCancelBookingAction must not import Payments models), `AppendOnlyTablesHaveNoSoftDeletesTest` (BookingAdminIntervention has no SoftDeletes trait)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: No dependencies — start immediately
- **Phase 2 (Foundation)**: Depends on Phase 1 (ADR must be Accepted before migration)
- **Phases 3–6 (User Stories)**: All depend on Phase 2 completion; can execute in priority order (P1→P2→P3→P4)
- **Phase 7 (Polish)**: Depends on all user story phases complete

### User Story Dependencies

- **US1 (P1 — Force Cancel)**: Foundation complete → standalone. First to implement.
- **US2 (P2 — Vendor Timeout)**: Foundation complete → standalone. No dependency on US1.
- **US3 (P3 — Vendor Proposal)**: Foundation complete → standalone. `RespondToVendorProposalAction` depends on `BookingAdminIntervention` model (Phase 2).
- **US4 (P4 — Admin Note)**: Foundation complete → simplest story. Can be done in parallel with US2/US3.

### Within Each User Story (InstaParty Layer Order)

```
ADR → Migration → Enum → Model → DTO → Event → Action → Listener → FormRequest → Resource → Controller → Route → Filament Action → Tests
```

### Parallel Opportunities Per Story

```
# Phase 2 — all [P] tasks can run together once T003+T004 migrations are applied:
T005 (InterventionType enum)  ←→  T006 (CustomerConsentStatus enum)  ←→  T007 (VendorSubStatus update)
T009 (AdminInterventionDTO)   ←→  T010 (VendorProposalResponseDTO)
T011-T014 (all 4 domain events — different files)

# Phase 3 — test tasks parallel:
T016 (happy path test)  ←→  T017 (refund per type test)  ←→  T018 (validation test)
# Implementation:
T019 (FormRequest)  ←→  T023 (API Resource)  # different files
T021 (Payments listener)  ←→  T022 (Communication listener)  # different modules

# Phase 5 — US3 most parallel-friendly:
T039 (ProposeRequest)  ←→  T040 (RespondRequest)  ←→  T045 (VendorProposalResource)
T035 (proposal tests)  ←→  T036 (FR-17 test)  ←→  T037 (consent test)  ←→  T038 (expiry test)
T043 (OnAlternativeVendorProposed)  ←→  T044 (OnVendorProposalDecided)  # different files
```

---

## Implementation Strategy

### Day 1 — Schema + Actions + Filament

1. Phase 1: Write ADR-0013, add permissions (T001–T002)
2. Phase 2: Run migrations, create enums/model/DTOs/events, update ServiceProvider (T003–T015)
3. Phase 3 Implementation: US1 force-cancel action + listeners + resource + controller + route + Filament (T019–T026)
4. Phase 4 Implementation: US2 vendor-timeout action + listener + controller + route + Filament (T029–T034)
5. Phase 5 Implementation: US3 proposal actions + listeners + resources + controllers + routes + Filament + expiry command (T039–T050)
6. Phase 6 Implementation: US4 admin-note action + controller + route + Filament (T053–T057)

### Day 2 — Tests + Polish

1. Phase 3 Tests: US1 (T016–T018)
2. Phase 4 Tests: US2 (T027–T028)
3. Phase 5 Tests: US3 (T035–T038) — most complex; allow most Day 2 time here
4. Phase 6 Tests: US4 (T051–T052)
5. Phase 7 Polish: list endpoint, ViewBooking page, translations, notification templates, shield, api-registry, arch tests (T058–T066)

### MVP Scope (Phase 3 Only)

If time is critically short: deliver US1 (force-cancel) alone. This satisfies the primary exit criterion "Admin can resolve stuck booking" and is the most urgent customer-facing rescue action.

---

## Notes

- Every `Action::execute()` wraps all mutations in `DB::transaction(fn() => ...)` — no exceptions
- Domain events fire via `DB::afterCommit()` — never inside the transaction
- All Communication and Payments listeners implement `ShouldQueue` — notifications and refunds are async
- `booking_state_transitions` gets a row ONLY for state-mutating interventions (force_cancel, vendor_timeout, accepted proposals) — NOT for admin_note or pending proposals
- `audit_logs` gets a row for EVERY intervention without exception
- FR-17 is enforced architecturally: `ProposeAlternativeVendorAction` never mutates `booking_vendors`; only `RespondToVendorProposalAction` (called as the customer) can advance to `accepted`
- Run `php artisan pint && ./vendor/bin/phpstan analyse` after each task group before proceeding
