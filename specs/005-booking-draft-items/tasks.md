# Tasks: Booking — Draft Creation & Item Management

**Input**: Design documents from `specs/005-booking-draft-items/`
**Feature branch**: `005-booking-draft-items`
**Prerequisites**: plan.md ✓, spec.md ✓, research.md ✓, data-model.md ✓, contracts/ ✓

**Tests**: Included — CLAUDE.md mandates coverage for all three product types on every type-aware feature.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no shared dependencies on incomplete tasks)
- **[Story]**: Which user story this task belongs to ([US1]–[US4])
- Exact file paths are included in all task descriptions

---

## Phase 1: Setup (Module Scaffold)

**Purpose**: Create the Booking module skeleton so all subsequent tasks have a home.

- [x] T001 Create `app/Modules/Booking/` directory tree matching the plan: `Domain/{Models,Enums,Events,States,Contracts}`, `Application/{Actions,DTOs,Listeners}`, `Infrastructure/Repositories`, `Console/Commands`, `Http/{Controllers/Customer,Requests,Resources}`, `Routes`, `Database/Migrations`, `Providers`
- [x] T002 Create `app/Modules/Booking/Providers/BookingServiceProvider.php` — register migrations (`loadMigrationsFrom`), routes, translations, listeners, policies; bind `BookingRepository` and `CatalogServiceReader` contracts in `register()`
- [x] T003 Register `BookingServiceProvider` in `bootstrap/providers.php` (or `config/app.php` providers array)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Migrations, models, enums, state machines, contracts, repositories, listeners, and the expiry command. **No user story work can begin until this phase is complete.**

⚠️ **CRITICAL**: Complete all tasks in this phase before moving to Phase 3.

### Enums

- [x] T004 [P] Create `app/Modules/Booking/Domain/Enums/LifecycleStatus.php` — backed enum with cases: `Draft`, `Submitted`, `VendorReview`, `CustomerReview`, `Confirmed`, `Active`, `Completed`, `Cancelled`
- [x] T005 [P] Create `app/Modules/Booking/Domain/Enums/PaymentStatus.php` — backed enum with cases: `Unpaid`, `Partial`, `Paid`, `RefundPending`, `PartiallyRefunded`, `Refunded`
- [x] T006 [P] Create `app/Modules/Booking/Domain/Enums/FulfillmentStatus.php` — backed enum with cases: `NotStarted`, `InProgress`, `PartiallyCompleted`, `Completed`, `Failed`
- [x] T007 [P] Create `app/Modules/Booking/Domain/Enums/VendorSubStatus.php` — backed enum with cases: `Pending`, `Accepted`, `Modified`, `Rejected`, `Cancelled`, `InProgress`, `Completed`

### Migrations (dependency order)

- [x] T008 Create `app/Modules/Booking/Database/Migrations/2026_04_30_000001_create_bookings_table.php` — all columns per DB schema §7: `id`, `public_id` CHAR(26), `reference_no` VARCHAR(20) UNIQUE, `customer_id` FK→users, `occasion_id` FK→occasions, three status ENUM columns (`lifecycle_status`, `payment_status`, `fulfillment_status`), event timestamps, money columns (`subtotal_minor`, `delivery_total_minor`, `discount_total_minor`, `loyalty_redeemed_minor`, `total_minor`, `amount_paid_minor` all BIGINT UNSIGNED + currency CHAR(3)), `tax_invoice_*`, `submitted_at`, `confirmed_at`, `cancelled_at`, `cancelled_by`, `timestamps()`, `softDeletes()`; all required indexes
- [x] T009 Create `app/Modules/Booking/Database/Migrations/2026_04_30_000002_create_booking_addresses_table.php` — FK→bookings, `city_id` FK→cities, address snapshot fields (`address_line`, `building`, `floor`, `apartment`, `landmark`, `latitude`, `longitude`, `recipient_name`, `recipient_phone_e164`), `timestamps()`
- [x] T010 Create `app/Modules/Booking/Database/Migrations/2026_04_30_000003_create_booking_snapshots_table.php` — append-only: `id`, `public_id`, `booking_id` FK, `version` INT UNSIGNED, `snapshot` JSON, `trigger_kind` ENUM, `trigger_reference_type`, `trigger_reference_id`, `triggered_by` FK→users NULL, `created_at` only (no `updated_at`); UNIQUE `(booking_id, version)`
- [x] T011 Create `app/Modules/Booking/Database/Migrations/2026_04_30_000004_create_booking_locks_table.php` — `id`, `resource_type` VARCHAR(80), `resource_id` BIGINT, `lock_token` CHAR(36), `locked_by_user_id` FK→users NULL, `lock_purpose` ENUM, `acquired_at`, `expires_at`, `released_at` NULL, `created_at`; UNIQUE `(resource_type, resource_id, released_at)`
- [x] T012 Create `app/Modules/Booking/Database/Migrations/2026_04_30_000005_create_booking_vendors_table.php` — `id`, `public_id`, `booking_id` FK→bookings, `vendor_profile_id` FK→vendor_profiles, `sub_status` ENUM DEFAULT `pending`, `response_deadline`, `responded_at` NULL, `rejection_reason` JSON NULL, `vendor_notes` JSON NULL, money columns (`subtotal_minor`, `delivery_fee_minor`, `commission_minor`, `vendor_payout_minor`), `timestamps()`; UNIQUE `(booking_id, vendor_profile_id)`; index `(vendor_profile_id, sub_status, response_deadline)`
- [x] T013 Create `app/Modules/Booking/Database/Migrations/2026_04_30_000006_create_booking_items_table.php` — `id`, `public_id`, `booking_vendor_id` FK→booking_vendors, `service_id` FK→services, `product_type` ENUM, `name_snapshot` JSON, money columns (`unit_price_minor`, `line_total_minor`, `commission_minor`), `quantity`, `effective_starts_at`, `effective_ends_at`, `has_item_slot_override` BOOLEAN, `customization_data` JSON NULL, `type_snapshot` JSON, `fulfillment_data` JSON NULL, `item_status` VARCHAR(40), `commission_bps` INT UNSIGNED, `timestamps()`; indexes `(booking_vendor_id)`, `(service_id)`, `(product_type, item_status)`
- [x] T014 Create `app/Modules/Booking/Database/Migrations/2026_04_30_000007_create_booking_state_transitions_table.php` — append-only: `id`, `transitionable_type` VARCHAR(120), `transitionable_id` BIGINT, `from_state` VARCHAR(40) NULL, `to_state` VARCHAR(40)`, `triggered_by` FK→users NULL, `trigger_kind` ENUM, `context` JSON NULL, `created_at` only (no `updated_at`, no soft delete); index `(transitionable_type, transitionable_id, created_at)`
- [x] T015 Create `app/Modules/Booking/Database/Migrations/2026_04_30_000008_create_booking_customer_notes_table.php` — `id`, `booking_id` FK→bookings, `user_id` FK→users, `body` TEXT, `detected_locale` ENUM, `created_at` only; index `(booking_id)`

### Domain Models

- [x] T016 [P] Create `app/Modules/Booking/Domain/Models/Booking.php` — traits: `HasPublicId`, `SoftDeletes`, `HasFactory`; `$fillable`, `$casts` (enums, datetimes, MoneyCast for all money column pairs); relationships: `hasOne(BookingAddress)`, `hasMany(BookingVendor)`, `hasManyThrough(BookingItem, BookingVendor)`, `hasMany(BookingSnapshot)`, `hasMany(BookingStateTransition)`; scopes: `scopeDraft()`, `scopeForCustomer(int $customerId)`
- [x] T017 [P] Create `app/Modules/Booking/Domain/Models/BookingAddress.php` — `$fillable`, `$casts`; relationship: `belongsTo(Booking)`
- [x] T018 [P] Create `app/Modules/Booking/Domain/Models/BookingVendor.php` — `$fillable`, `$casts` (VendorSubStatus, MoneyCast); relationships: `belongsTo(Booking)`, `hasMany(BookingItem)`; UNIQUE scope
- [x] T019 [P] Create `app/Modules/Booking/Domain/Models/BookingItem.php` — `$fillable`, `$casts` (ProductType enum, MoneyCast); relationships: `belongsTo(BookingVendor)`, `belongsTo(Service)` (read-only); `resolveItemState()` method using `match($this->product_type)` to return correct state hierarchy instance
- [x] T020 [P] Create `app/Modules/Booking/Domain/Models/BookingLock.php` — `$fillable`, `$casts`; static method `acquireOrFail(string $resourceType, int $resourceId, string $purpose, int $ttlSeconds = 30): self`
- [x] T021 [P] Create `app/Modules/Booking/Domain/Models/BookingSnapshot.php` — append-only: `$fillable`, NO `$timestamps` (only `created_at`), cast `snapshot` as `array`; relationship `belongsTo(Booking)`
- [x] T022 [P] Create `app/Modules/Booking/Domain/Models/BookingStateTransition.php` — append-only: `$fillable`, NO `$timestamps`, polymorphic `morphTo('transitionable')`
- [x] T023 [P] Create `app/Modules/Booking/Domain/Models/BookingCustomerNote.php` — `$fillable`, NO `updated_at`; relationship `belongsTo(Booking)`

### Per-Type State Machines

- [x] T024 [P] Create Rental state hierarchy in `app/Modules/Booking/Domain/States/RentalItemStatus/`: abstract `RentalItemStatus.php` (extends `spatie\ModelStates\State`), plus `PendingDeliveryState.php`, `OutForDeliveryState.php`, `DeliveredState.php`, `SetupCompleteState.php`, `PickedUpState.php`; define allowed transitions in each state class
- [x] T025 [P] Create Sale state hierarchy in `app/Modules/Booking/Domain/States/SaleItemStatus/`: abstract `SaleItemStatus.php`, plus `PendingState.php`, `InPreparationState.php`, `ReadyState.php`, `DeliveredState.php`; define allowed transitions
- [x] T026 [P] Create Digital state hierarchy in `app/Modules/Booking/Domain/States/DigitalItemStatus/`: abstract `DigitalItemStatus.php`, plus `PendingState.php`, `SentState.php`, `RedeemedState.php`; define allowed transitions

### Contracts & Repository

- [x] T027 [P] Create `app/Modules/Booking/Domain/Contracts/BookingRepository.php` — interface: `create(CreateBookingDraftDTO): Booking`, `findDraftForCustomer(int $bookingId, int $customerId): ?Booking`, `findByPublicId(string $publicId): ?Booking`
- [x] T028 [P] Create `app/Modules/Booking/Domain/Contracts/CatalogServiceReader.php` — interface: `findPublishedById(int $id): ?ServiceReadDTO` where `ServiceReadDTO` is a plain readonly class in `app/Modules/Booking/Application/DTOs/ServiceReadDTO.php` carrying: `id`, `publicId`, `vendorProfileId`, `productType`, `nameEn`, `nameAr`, `basePriceMinor`, `basePriceCurrency`, `stockQuantity`
- [x] T029 Create `app/Modules/Booking/Infrastructure/Repositories/EloquentBookingRepository.php` — implements `BookingRepository`; all methods wrap queries in transactions where needed; uses Eloquent, not cross-module model imports (Service is accessed via `ServiceReadDTO` from CatalogServiceReader)
- [x] T030 Bind `CatalogServiceReader` contract: in `app/Modules/Catalog/Providers/CatalogServiceProvider.php::register()` add binding of `CatalogServiceReader::class` to a new `app/Modules/Catalog/Infrastructure/Repositories/EloquentCatalogServiceReader.php` that reads from `services` table and returns `ServiceReadDTO`

### Domain Events

- [x] T031 [P] Create `app/Modules/Booking/Domain/Events/BookingDraftCreated.php` — constructor takes `Booking $booking`; `implements ShouldBroadcast` if needed (Phase 3 concern — for now just `Event`)
- [x] T032 [P] Create `app/Modules/Booking/Domain/Events/BookingItemAdded.php` — constructor takes `BookingItem $item`
- [x] T033 [P] Create `app/Modules/Booking/Domain/Events/BookingItemRemoved.php` — constructor takes `int $bookingVendorId`, `int $bookingId`, `int $removedLineTotal`

### Listeners

- [x] T034 [P] Create `app/Modules/Booking/Application/Listeners/WriteInitialBookingSnapshotListener.php` — listens to `BookingDraftCreated`; creates `BookingSnapshot` record with `version=1`, `trigger_kind='booking_created'`, `snapshot` JSON with booking + address
- [x] T035 [P] Create `app/Modules/Booking/Application/Listeners/RecalculateBookingTotalsListener.php` — listens to `BookingItemAdded` and `BookingItemRemoved`; re-aggregates `booking_vendors.subtotal_minor` and `bookings.total_minor` via DB queries (see research.md R4)
- [x] T036 [P] Create `app/Modules/Booking/Application/Listeners/WriteBookingStateTransitionListener.php` — listens to `BookingDraftCreated`; appends row to `booking_state_transitions` with `from_state=null`, `to_state='draft'`
- [x] T037 Register all three listeners in `BookingServiceProvider::boot()` via `Event::listen([...])`

### Scheduled Command

- [x] T038 Create `app/Modules/Booking/Console/Commands/ReleaseExpiredReservationsCommand.php` — signature: `booking:release-expired-reservations`; chunks through `service_inventory_reservations` where `status='held' AND expires_at < now()`; updates each to `status='expired', released_at=now()` inside `DB::transaction`; register in `BookingServiceProvider::boot()` via `$this->commands([...])`

**Checkpoint**: All migrations, models, state machines, contracts, listeners, and the expiry command are in place. User story implementation can now begin.

---

## Phase 3: User Story 1 — Create a Draft Booking (Priority: P1) 🎯 MVP

**Goal**: An authenticated customer can POST to create a draft booking with event details and address snapshot.

**Independent Test**: `CreateBookingDraftTest` — POST `/api/v1/customer/bookings` with valid payload returns 201 with `public_id` and `lifecycle_status = draft`.

### Implementation

- [x] T039 [P] [US1] Create `app/Modules/Booking/Application/DTOs/CreateBookingDraftDTO.php` — readonly class: `customerId`, `occasionId`, `eventStartsAt`, `eventEndsAt`, `guestCount`, `theme`, `celebrantName`, `celebrantDob`, `celebrantGender`, address fields as nested object or flat properties
- [x] T040 [P] [US1] Create `app/Modules/Booking/Http/Requests/CreateBookingDraftRequest.php` — validation rules per contract: `occasion_id` (required, exists:occasions,public_id), `event_starts_at` (required, date, after:now), `event_ends_at` (required, date, after:event_starts_at), address sub-object rules; `toDTO()` method returning `CreateBookingDraftDTO`
- [x] T041 [US1] Create `app/Modules/Booking/Application/Actions/CreateBookingDraftAction.php` — constructor injects `BookingRepository`, `CatalogServiceReader`; `execute(CreateBookingDraftDTO): Booking`; wraps in `DB::transaction`: resolve occasion_id, generate `public_id` (Str::ulid()), generate `reference_no` (`IP-{Y}-{id padded 6}`), create Booking with `lifecycle_status=draft`, create BookingAddress snapshot, fire `BookingDraftCreated` via `DB::afterCommit`
- [x] T042 [US1] Create `app/Modules/Booking/Http/Resources/BookingResource.php` — transforms Booking model; converts timestamps to customer timezone (from `auth()->user()->timezone`); exposes `public_id`, `reference_no`, status fields, money amounts, `vendors` (empty array at creation), `address`
- [x] T043 [US1] Create `app/Modules/Booking/Http/Controllers/Customer/BookingController.php` — `store(CreateBookingDraftRequest $request): JsonResponse` — 3-line body: `$booking = app(CreateBookingDraftAction::class)->execute($request->toDTO()); return ApiResponse::created(new BookingResource($booking));`
- [x] T044 [US1] Create `app/Modules/Booking/Routes/customer.php` — route group: prefix `api/v1/customer`, middleware `[auth:sanctum, role:customer]`; `POST /bookings` → `BookingController@store`; also define `GET /bookings/{publicId}` → `BookingController@show` (returns booking with current vendors/items)
- [x] T045 [P] [US1] Write `tests/Feature/Modules/Booking/CreateBookingDraftTest.php` — test cases: (1) authenticated customer + valid payload → 201 with `public_id` and `lifecycle_status=draft`; (2) unauthenticated → 401; (3) missing `event_starts_at` → 422; (4) missing `address.city_id` → 422; (5) verify `booking_snapshots` row created with `version=1`; (6) verify `booking_state_transitions` row with `to_state=draft`

**Checkpoint**: `CreateBookingDraftTest` passes. Draft booking creation is independently functional.

---

## Phase 4: User Story 2 — Add Items from Multiple Vendors (Priority: P1)

**Goal**: An authenticated customer can add rental, sale, and digital items from multiple vendors to a draft booking, with per-type inventory reservation and vendor grouping.

**Independent Test**: `AddItemToBookingTest` — POST items from 2 vendors → 2 `booking_vendor` rows; rental/sale items have `service_inventory_reservations` records.

### Implementation

- [x] T046 [P] [US2] Create `app/Modules/Booking/Application/DTOs/AddBookingItemDTO.php` — readonly class: `bookingId`, `customerId`, `serviceId` (internal int), `quantity`, `effectiveStartsAt`, `effectiveEndsAt`, `customizationData`
- [x] T047 [P] [US2] Create `app/Modules/Booking/Http/Requests/AddBookingItemRequest.php` — rules: `service_id` (required, exists:services,public_id), `quantity` (required, integer, min:1), `effective_starts_at` (nullable, date), `effective_ends_at` (nullable, date, after:effective_starts_at), `customization_data` (nullable, array); `toDTO(int $bookingId, int $customerId)` method that resolves service `public_id` → internal id
- [x] T048 [US2] Create `app/Modules/Booking/Application/Actions/AddItemToBookingAction.php` — constructor injects `BookingRepository`, `CatalogServiceReader`; `execute(AddBookingItemDTO): BookingItem`; wraps in `DB::transaction`: (1) find draft booking and verify customer ownership (403 if mismatch, 409 if not draft), (2) load service via `CatalogServiceReader::findPublishedById()`, (3) lock service row with `Service::lockForUpdate()->findOrFail()`, (4) per-type inventory check and reservation via `match($service->productType)` (rental: date-range overlap check, sale: stock count check, digital: skip), (5) upsert `BookingVendor` with `firstOrCreate(['booking_id'=>..., 'vendor_profile_id'=>...])`, (6) create `BookingItem` with `item_status` set to per-type initial state, (7) fire `BookingItemAdded` via `DB::afterCommit`
- [x] T049 [US2] Create `app/Modules/Booking/Http/Resources/BookingItemResource.php` and `app/Modules/Booking/Http/Resources/BookingVendorResource.php` — expose item fields including `reservation_expires_at`; vendor resource includes `subtotal_minor`, `delivery_fee_minor`
- [x] T050 [US2] Create `app/Modules/Booking/Http/Controllers/Customer/BookingItemController.php` — `store(AddBookingItemRequest $request, string $bookingPublicId): JsonResponse` — 3-line body; resolves `bookingPublicId` → internal id in `BookingRepository`
- [x] T051 [US2] Add route to `app/Modules/Booking/Routes/customer.php`: `POST /bookings/{bookingPublicId}/items` → `BookingItemController@store`
- [x] T052 [P] [US2] Write `tests/Feature/Modules/Booking/AddItemToBookingTest.php` — test cases per data-model.md test plan: (1) add rental item → 201, reservation row created, `item_status=pending_delivery`; (2) add sale item → 201, reservation row created, `item_status=pending`; (3) add digital item → 201, no reservation row, `item_status=pending`; (4) add second item from same vendor → only one `booking_vendor` row, subtotal updated; (5) add items from two vendors → two `booking_vendor` rows; (6) add out-of-stock rental → 409; (7) add to non-draft booking → 409; (8) add to another user's booking → 403; (9) unauthenticated → 401
- [x] T053 [P] [US2] Write `tests/Unit/Modules/Booking/States/RentalItemStateTest.php`, `SaleItemStateTest.php`, `DigitalItemStateTest.php` — unit tests: verify initial state, verify allowed transitions are accepted, verify forbidden transitions throw `\Spatie\ModelStates\Exceptions\TransitionNotFound`

**Checkpoint**: `AddItemToBookingTest` passes for all three product types. Multi-vendor item addition is independently functional.

---

## Phase 5: User Story 3 — Remove an Item from the Draft Booking (Priority: P2)

**Goal**: An authenticated customer can delete a booking item; its inventory reservation is released, and the booking_vendor row is removed if no items remain for that vendor.

**Independent Test**: `RemoveItemFromBookingTest` — DELETE item → reservation status becomes `released`, booking totals decrease, vendor row removed if empty.

### Implementation

- [x] T054 [US3] Create `app/Modules/Booking/Application/Actions/RemoveItemFromBookingAction.php` — constructor injects `BookingRepository`; `execute(int $bookingId, int $customerId, int $itemId): void`; wraps in `DB::transaction`: (1) find draft + ownership check, (2) load item and verify it belongs to this booking (403 otherwise), (3) update `service_inventory_reservations` → `status=released, released_at=now(), release_reason=customer_cancelled`, (4) delete `BookingItem`, (5) if `BookingVendor` has zero remaining items: delete `BookingVendor`, (6) fire `BookingItemRemoved` via `DB::afterCommit`
- [x] T055 [US3] Add `destroy(string $bookingPublicId, string $itemPublicId): JsonResponse` method to `app/Modules/Booking/Http/Controllers/Customer/BookingItemController.php` — 3-line body; resolves both public IDs to internal IDs
- [x] T056 [US3] Add route to `app/Modules/Booking/Routes/customer.php`: `DELETE /bookings/{bookingPublicId}/items/{itemPublicId}` → `BookingItemController@destroy`
- [x] T057 [P] [US3] Write `tests/Feature/Modules/Booking/RemoveItemFromBookingTest.php` — test cases: (1) remove item (vendor still has other items) → 200, reservation released, subtotal updated; (2) remove last item for a vendor → vendor row deleted; (3) remove from non-draft booking → 409; (4) remove item from another user's booking → 403; (5) unauthenticated → 401

**Checkpoint**: `RemoveItemFromBookingTest` passes. Item removal independently functional.

---

## Phase 6: User Story 4 — Per-Vendor Cost Breakdown (Priority: P2)

**Goal**: Customer retrieves a booking and sees accurate per-vendor subtotals, delivery fees, and a grand total.

**Independent Test**: `BookingTotalsTest` — GET booking after adding items from 2 vendors → response contains per-vendor breakdown that sums to grand total.

### Implementation

- [x] T058 [US4] Implement `BookingController::show(string $publicId): JsonResponse` in `app/Modules/Booking/Http/Controllers/Customer/BookingController.php` — loads booking with `->with(['bookingVendors.bookingItems', 'bookingAddress'])` for customer; verifies customer ownership; returns full `BookingResource`
- [x] T059 [US4] Extend `app/Modules/Booking/Http/Resources/BookingResource.php` — add `vendors` array: each vendor entry includes `BookingVendorResource` with items (via `BookingItemResource`), `subtotal_minor`, `delivery_fee_minor`; add `grand_total_minor = subtotal + delivery_total - discount`; locale-aware `name` fields (EN or AR per `Accept-Language`)
- [x] T060 [P] [US4] Write `tests/Feature/Modules/Booking/BookingTotalsTest.php` — test cases: (1) add item qty 2 → `line_total_minor = unit_price_minor × 2`; (2) two vendors → `meta.booking_total_minor = vendor_A_subtotal + vendor_A_delivery + vendor_B_subtotal + vendor_B_delivery`; (3) remove item → totals decrease by line total; (4) locale EN response → names in English; (5) locale AR response → names in Arabic

**Checkpoint**: `BookingTotalsTest` passes. All four user stories independently functional.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Scheduler wiring, Filament admin visibility, quickstart validation, code quality.

- [x] T061 [P] Register `ReleaseExpiredReservationsCommand` in the scheduler: in `routes/console.php` (Laravel 12) or `app/Console/Kernel.php` add `$schedule->command('booking:release-expired-reservations')->everyMinute()->withoutOverlapping()`
- [x] T062 [P] Create a Filament widget stub for booking monitoring: `app/Modules/Booking/Filament/Widgets/BookingStatsWidget.php` — extends `StatsOverviewWidget`; shows draft count, submitted count, confirmed count (today); registered in `BookingServiceProvider`
- [x] T063 Run `php artisan shield:generate --all` — verify no new permissions are needed beyond customer-owned booking access (Filament widget access controlled by admin role)
- [x] T064 Validate `quickstart.md` smoke test: run the curl commands from `specs/005-booking-draft-items/quickstart.md` against a local Docker environment; confirm all responses match documented shapes
- [x] T065 [P] Run `./vendor/bin/pint app/Modules/Booking/` and fix any style violations
- [x] T066 [P] Run `./vendor/bin/phpstan analyse app/Modules/Booking/` at level 6; resolve any type errors

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately
- **Foundational (Phase 2)**: Depends on Phase 1 — **BLOCKS all user stories**
- **User Story Phases (3–6)**: All depend on Phase 2 completion
  - US1 (Phase 3) and US2 (Phase 4) are both P1 — can run in parallel after Phase 2
  - US3 (Phase 5) and US4 (Phase 6) can start after Phase 2, but naturally compose on top of US1/US2
- **Polish (Phase 7)**: Depends on all user stories being complete

### User Story Dependencies

- **US1 (Create Draft)**: No US dependencies — standalone entry point
- **US2 (Add Items)**: Requires US1 — you need a booking to add items to; however US2 can be developed in parallel with US1 since models and the action are independent
- **US3 (Remove Item)**: Requires US2 — an item must exist to be removed; but `RemoveItemFromBookingAction` can be written independently
- **US4 (View Breakdown)**: Requires US1 and US2 — needs a booking with items; the `show` endpoint and resource changes are otherwise standalone

### Within Each User Story

- Enums → Models → Contracts → Repositories → Actions → HTTP layer → Tests
- Tests can be written concurrently with Actions (different files, [P] where marked)
- State machines [P] with models — different files

### Parallel Opportunities

All tasks marked [P] across a phase can launch simultaneously. Key groups:

**Phase 2 parallel batches**:
- Batch A (T004–T007): All four enums
- Batch B (T008–T015): Migrations (order matters within batch — T008 before T012 before T013; T009 before T010)
- Batch C (T016–T023): All eight models
- Batch D (T024–T026): Three state machine hierarchies
- Batch E (T027–T028): Two contracts
- Batch F (T031–T033): Three events

---

## Parallel Example: Phase 2 — Models + State Machines

```bash
# Launch all 8 models in parallel (different files):
Task: "Create Booking.php"             # T016
Task: "Create BookingAddress.php"      # T017
Task: "Create BookingVendor.php"       # T018
Task: "Create BookingItem.php"         # T019
Task: "Create BookingLock.php"         # T020
Task: "Create BookingSnapshot.php"     # T021
Task: "Create BookingStateTransition.php" # T022
Task: "Create BookingCustomerNote.php" # T023

# Simultaneously: all 3 state machine hierarchies
Task: "Create Rental state hierarchy"  # T024
Task: "Create Sale state hierarchy"    # T025
Task: "Create Digital state hierarchy" # T026
```

## Parallel Example: User Story 2 (Add Items)

```bash
# Tests and DTO/Request can be written in parallel with the Action:
Task: "Create AddBookingItemDTO.php"         # T046
Task: "Create AddBookingItemRequest.php"     # T047
Task: "Write AddItemToBookingTest.php"       # T052
Task: "Write state machine unit tests"       # T053

# Then after T046–T048:
Task: "Implement AddItemToBookingAction.php" # T048 (depends on T046, T047)
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Module scaffold
2. Complete Phase 2: Foundation (migrations, models, state machines, contracts, listeners, command)
3. Complete Phase 3: Draft creation + Pest tests
4. **STOP and VALIDATE**: `./vendor/bin/pest tests/Feature/Modules/Booking/CreateBookingDraftTest.php`
5. Draft booking creation works end-to-end

### Incremental Delivery

1. Setup + Foundation → booking tables and models ready
2. US1 (Draft creation) → testable, deployable MVP
3. US2 (Add items, all 3 types) → basket functionality live
4. US3 (Remove items) → basket management complete
5. US4 (Cost breakdown) → customer sees full pricing — ready for Phase 3.2 (payment)

### Parallel Team Strategy

With two developers after Phase 2:
- **Developer A**: US1 (Draft creation) then US4 (Breakdown resource)
- **Developer B**: US2 (Add items) then US3 (Remove items)

---

## Notes

- [P] tasks = different files, safe to parallelize
- All Actions use `DB::transaction` + `DB::afterCommit` for events (rule from CLAUDE.md)
- Never use `if/elseif` on product_type strings — always `match($enum)` (T048 critical)
- `booking_snapshots` and `booking_state_transitions` are append-only — no `updated_at`, no soft delete
- Confirm three state machine test files (T053) cover all 9 states and all transition rules
- Run `./vendor/bin/pest --group=booking` to run full suite before Phase 3.2
