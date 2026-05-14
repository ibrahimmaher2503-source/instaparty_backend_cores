# Tasks: Admin Changes-Requested Workflow

**Feature**: `020-admin-changes-request` | **Date**: 2026-05-04
**Input**: `specs/020-admin-changes-request/`
**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md) | **Data Model**: [data-model.md](data-model.md) | **Contracts**: [contracts/api.md](contracts/api.md)

**Tests**: Pest tests are explicitly required per the feature spec deliverable (Pest mentioned under Day 1 and Day 2 tasks).

**Organization**: Tasks are grouped by user story. Foundation tasks (Phase 2) must complete before any user story work begins.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on other incomplete tasks)
- **[Story]**: Which user story this task belongs to (US1, US2, US3)

---

## Phase 1: Setup

**Purpose**: ADR must be accepted before any migration is created — this is a Constitution §VI hard gate.

- [ ] T001 Write and accept ADR-0018 in `docs/adr/ADR-0018-changes-requested-workflow.md` — document polymorphic subject strategy (explicit ENUM discriminator, not Laravel morphTo), module ownership (Shared), cycle-cap constant, escalation trigger mechanism (manual Phase 1 / cron Phase 1.5)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Shared-module infrastructure that BOTH US1 and US2 depend on — enums, interface, models, migrations, base HTTP layer.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [ ] T002 [P] Create `ChangeRequestStatus` backed enum in `app/Modules/Shared/Domain/Enums/ChangeRequestStatus.php` — cases: `Open`, `Resubmitted`, `Resolved`, `EscalatedToRejection`
- [ ] T003 [P] Create `ChangeRequestItemStatus` backed enum in `app/Modules/Shared/Domain/Enums/ChangeRequestItemStatus.php` — cases: `Pending`, `Addressed`, `Waived`
- [ ] T004 [P] Create `ChangeRequestSubjectType` backed enum in `app/Modules/Shared/Domain/Enums/ChangeRequestSubjectType.php` — cases: `VendorProfile = 'vendor_profile'`, `Service = 'service'`
- [ ] T005 [P] Create `ChangeRequestSubject` interface in `app/Modules/Shared/Domain/Contracts/ChangeRequestSubject.php` — methods: `getChangeRequestSubjectType(): ChangeRequestSubjectType`, `getKey(): int`
- [ ] T006 [P] Create `ChangeRequestPolicy` class in `app/Modules/Shared/Domain/Policies/ChangeRequestPolicy.php` — constant `MAX_CYCLES = 3`; no instance methods needed in Phase 1
- [ ] T007 Create migration `2026_05_04_000001_create_change_requests_table.php` in `app/Modules/Shared/Database/Migrations/` — columns: `id`, `public_id CHAR(26)`, `subject_type ENUM('vendor_profile','service')`, `subject_id BIGINT`, `requested_by_admin_id FK→users`, `status ENUM`, `cycle_number TINYINT`, `resolution_notes TEXT NULL`, `resolved_by_admin_id FK→users NULL`, `resolved_at TIMESTAMP NULL`, `created_at` (no `updated_at`); indexes: `(subject_type, subject_id, status)`, `(requested_by_admin_id)`, `(status, created_at)`
- [ ] T008 Create migration `2026_05_04_000002_create_change_request_items_table.php` in `app/Modules/Shared/Database/Migrations/` — columns: `id`, `public_id CHAR(26)`, `change_request_id FK→change_requests(id) restrictOnDelete`, `field_path VARCHAR(255)`, `current_value_snapshot JSON NULL`, `requested_change_en TEXT`, `requested_change_ar TEXT`, `item_status ENUM('pending','addressed','waived')`, `created_at` (no `updated_at`); index: `(change_request_id, item_status)`
- [ ] T009 Create `ChangeRequest` Eloquent model in `app/Modules/Shared/Domain/Models/ChangeRequest.php` — casts `status` → `ChangeRequestStatus`, `cycle_number` → int; relationships: `items()` HasMany, `requestedByAdmin()` BelongsTo→User, `resolvedByAdmin()` BelongsTo→User; scopes: `scopeOpen()`, `scopeResubmitted()`, `scopeForSubject(string $type, int $id)`; trait `HasPublicId`; no `updated_at` in `$dates`
- [ ] T010 Create `ChangeRequestItem` Eloquent model in `app/Modules/Shared/Domain/Models/ChangeRequestItem.php` — casts `item_status` → `ChangeRequestItemStatus`, `current_value_snapshot` → array; relationship: `changeRequest()` BelongsTo→ChangeRequest; trait `HasPublicId`; no `updated_at`
- [ ] T011 Create `ChangeRequestResource` API resource in `app/Modules/Shared/Http/Resources/ChangeRequestResource.php` — includes `public_id`, `subject_type`, `subject_id_public`, `status`, `cycle_number`, `items` (nested `ChangeRequestItemResource`), `created_at`; add `@response` PHPDoc with EN+AR example data
- [ ] T012 Create `ChangeRequestItemResource` API resource in `app/Modules/Shared/Http/Resources/ChangeRequestItemResource.php` — includes `public_id`, `field_path`, `requested_change_en`, `requested_change_ar`, `item_status`
- [ ] T013 Create `VendorChangeRequestListController` in `app/Modules/Shared/Http/Controllers/VendorChangeRequestListController.php` — `index()` action, 3 lines max, delegates to query on `ChangeRequest` scoped to authenticated vendor's profiles + services; paginate by `per_page`; filter by `status`
- [x] T014 Create `ChangeRequestNotificationTemplateSeeder` in `app/Modules/Communication/Database/Seeders/ChangeRequestNotificationTemplateSeeder.php` — seeds `vendor.changes_requested` (push + email, EN + AR) and `vendor.resubmitted` (push + email, EN + AR) into `notification_templates`
- [x] T015 Add Shared vendor route for `GET /api/v1/vendor/change-requests` in `app/Modules/Shared/Routes/vendor.php` — guard: `auth:sanctum` + `role:vendor`; note if file does not yet exist, create it and register in `SharedServiceProvider`

**Checkpoint**: Foundation ready — enums, interface, models, migrations, and base HTTP layer are all in place.

---

## Phase 3: User Story 1 — Admin Requests Document Changes from Vendor (Priority: P1) 🎯 MVP

**Goal**: Admin can flag specific corrections on a vendor profile; vendor receives bilingual notification, sees the checklist, addresses items, and resubmits to the approval queue. Full cycle is traceable.

**Independent Test**: Create vendor in `pending` status → admin POSTs change request → vendor profile becomes `changes_requested` → vendor POSTs resubmit → vendor profile returns to `pending` → run `./vendor/bin/pest --filter=VendorChangesRequestedTest`.

### Tests for User Story 1

> **Write tests FIRST — confirm they FAIL before writing implementation code**

- [x] T016 [P] [US1] Write `VendorChangesRequestedTest.php` in `tests/Feature/Modules/Identity/VendorChangesRequestedTest.php` — test cases: happy-path full cycle (pending → changes_requested → resubmit → pending), 201 response shape matches contract, `change_requests` record created with `cycle_number=1`, `change_request_items` records with `item_status=pending`, vendor profile status is `changes_requested` after request, vendor profile status is `pending` after resubmit, change request status is `resubmitted` after resubmit, both EN and AR fields persisted exactly, auth checks (401 on no token), role checks (403 for non-admin on request-changes, 403 for non-vendor on resubmit), ownership check (403 if vendor resubmits another vendor's profile), idempotency (409 on duplicate Idempotency-Key), 409 if profile already has open change request, 422 if `requested_change_ar` is empty string

### Implementation for User Story 1

- [x] T017 [US1] Add `ChangesRequested = 'changes_requested'` case and `isActionable(): bool` helper to `ApprovalStatus` enum in `app/Modules/Identity/Domain/Enums/ApprovalStatus.php`
- [x] T018 [US1] Create migration `2026_05_04_000003_add_changes_requested_to_vendor_profiles_approval_status.php` in `app/Modules/Identity/Database/Migrations/` — MySQL `MODIFY COLUMN approval_status ENUM('pending','approved','rejected','suspended','changes_requested')`
- [x] T019 [US1] Implement `ChangeRequestSubject` interface on `VendorProfile` model in `app/Modules/Identity/Domain/Models/VendorProfile.php` — `getChangeRequestSubjectType()` returns `ChangeRequestSubjectType::VendorProfile`; `getKey()` returns `$this->id`
- [x] T020 [US1] Create `RequestVendorChangesFormRequest` in `app/Modules/Identity/Http/Requests/RequestVendorChangesFormRequest.php` — validates `items` (required array min:1), `items.*.field_path` (required string max:255), `items.*.requested_change_en` (required string min:5), `items.*.requested_change_ar` (required string min:5); add `@bodyParam` PHPDoc on each field
- [x] T021 [P] [US1] Create `VendorResubmitFormRequest` in `app/Modules/Identity/Http/Requests/VendorResubmitFormRequest.php` — validates `addressed_item_ids` (required array), `addressed_item_ids.*` (string, must be valid `change_request_items.public_id` belonging to this request), `waived_item_ids` (optional array), `resubmit_notes` (optional string max:1000); add `@bodyParam` PHPDoc on each field
- [x] T022 [US1] Create `RequestVendorChangesAction` in `app/Modules/Identity/Application/Actions/RequestVendorChangesAction.php` — `execute(VendorProfile $profile, array $items, User $admin, string $idempotencyKey): ChangeRequest`; guards: check idempotency_keys table (return cached on match), assert no open/resubmitted request exists for subject, assert `cycle_number` would not exceed `ChangeRequestPolicy::MAX_CYCLES`; within `DB::transaction`: compute next cycle_number, create `change_requests` row, create `change_request_items` rows (snapshot current field values), update `vendor_profile.approval_status = 'changes_requested'`; fire `ChangeRequestCreated` event via `DB::afterCommit()`; store idempotency result
- [x] T023 [US1] Create `VendorResubmitAfterChangesAction` in `app/Modules/Identity/Application/Actions/VendorResubmitAfterChangesAction.php` — `execute(VendorProfile $profile, array $addressedItemIds, array $waivedItemIds, ?string $notes, string $idempotencyKey): ChangeRequest`; guards: assert `approval_status === ChangesRequested`, assert active change request has `status=open`; within `DB::transaction`: update addressed item_status→`addressed`, waived→`waived`, update change_request `status=resubmitted`, update vendor_profile `approval_status=pending`; fire `VendorProfileResubmitted` via `DB::afterCommit()`; store idempotency result
- [x] T024 [US1] Create `VendorChangeRequestController` (admin) in `app/Modules/Identity/Http/Controllers/VendorChangeRequestController.php` — `store(RequestVendorChangesFormRequest $request, string $publicId)` — 3-line body: resolve profile, call Action, return ChangeRequestResource 201
- [x] T025 [US1] Create `VendorProfileResubmitController` (vendor) in `app/Modules/Identity/Http/Controllers/VendorProfileResubmitController.php` — `store(VendorResubmitFormRequest $request, string $publicId)` — 3-line body: resolve profile (ownership check), call Action, return response 200
- [x] T026 [US1] Add `POST /api/v1/admin/vendor-profiles/{publicId}/change-requests` route to `app/Modules/Identity/Routes/admin.php` — middleware: `auth:sanctum`, `role:admin`, `can:update_vendor_profile`; requires `Idempotency-Key` header check
- [ ] T027 [US1] Add `POST /api/v1/vendor/vendor-profiles/{publicId}/resubmit` route to `app/Modules/Identity/Routes/vendor.php` — middleware: `auth:sanctum`, `role:vendor`; requires `Idempotency-Key` header check
- [ ] T028 [US1] Add "Request Changes" Filament table action to `VendorProfileResource` in `app/Modules/Identity/Filament/Resources/VendorProfileResource.php` — uses `Repeater` form with `TextInput` (field_path), `TextInput` (requested_change_en), `TextInput` (requested_change_ar, `->dir('rtl')`); visible when `approval_status` is `Pending` or `ChangesRequested` AND no active `open` change request; delegates to `RequestVendorChangesAction::execute()`; shows success `Notification::make()->success()`
- [ ] T029 [P] [US1] Create `ChangeRequestCreated` domain event in `app/Modules/Shared/Domain/Events/ChangeRequestCreated.php` — constructor: `public ChangeRequest $changeRequest`
- [ ] T030 [P] [US1] Create `VendorProfileResubmitted` domain event in `app/Modules/Identity/Domain/Events/VendorProfileResubmitted.php` — constructor: `public VendorProfile $vendorProfile`, `public ChangeRequest $changeRequest`
- [ ] T031 [US1] Create `DispatchVendorChangesRequestedNotificationListener` in `app/Modules/Identity/Application/Listeners/DispatchVendorChangesRequestedNotificationListener.php` — handles `ChangeRequestCreated`; calls `DispatchNotificationAction` with event_key `vendor.changes_requested` for the vendor user; uses `DB::afterCommit()` pattern (queued listener)
- [ ] T032 [P] [US1] Create `DispatchVendorResubmittedNotificationListener` in `app/Modules/Identity/Application/Listeners/DispatchVendorResubmittedNotificationListener.php` — handles `VendorProfileResubmitted`; calls `DispatchNotificationAction` with event_key `vendor.resubmitted` for the reviewing admin
- [ ] T033 [US1] Register `ChangeRequestCreated → DispatchVendorChangesRequestedNotificationListener` and `VendorProfileResubmitted → DispatchVendorResubmittedNotificationListener` event-listener bindings in `app/Modules/Identity/Providers/IdentityServiceProvider.php`

**Checkpoint**: User Story 1 fully functional — run `./vendor/bin/pest --filter=VendorChangesRequestedTest --group=vendor-changes` and validate all test cases pass.

---

## Phase 4: User Story 2 — Admin Requests Changes on a Service Under Moderation (Priority: P2)

**Goal**: Admin can request itemized changes on any of the three product-type services; vendor addresses and resubmits; history is permanently linked. Covers all 3 product types (rental, sale, digital).

**Independent Test**: Create one rental service, one sale service, one digital service all in `pending_review` → admin POSTs change request on each → vendor resubmits each → run `./vendor/bin/pest --filter=ServiceChangesRequestedTest --group=service-changes`.

### Tests for User Story 2

> **Write tests FIRST — confirm they FAIL before writing implementation code**

- [ ] T034 [P] [US2] Write `ServiceChangesRequestedTest.php` in `tests/Feature/Modules/Catalog/ServiceChangesRequestedTest.php` — test cases for ALL 3 PRODUCT TYPES using `->group('rental')`, `->group('sale')`, `->group('digital')` markers: happy-path full cycle per type (pending_review → changes_requested → resubmit → pending_review), 201 response shape, `change_requests` record with `subject_type=service`, item `item_status=pending` after request, item `item_status=addressed` after resubmit for addressed items, service status returns to `pending_review` after resubmit, change request stays linked after service reaches `published`, auth/role/ownership guards, idempotency, 409 on duplicate open request, 422 on empty AR text

### Implementation for User Story 2

- [ ] T035 [US2] Add `ChangesRequested = 'changes_requested'` case to `ServiceStatus` enum and update `canTransitionTo()` in `app/Modules/Catalog/Domain/Enums/ServiceStatus.php` — add transitions: `PendingReview → ChangesRequested` and `ChangesRequested → PendingReview`; add `color()` case: `ChangesRequested => 'warning'`
- [ ] T036 [US2] Create migration `2026_05_04_000004_add_changes_requested_to_services_status.php` in `app/Modules/Catalog/Database/Migrations/` — MySQL `MODIFY COLUMN status ENUM('draft','pending_review','published','archived','changes_requested')`
- [ ] T037 [US2] Implement `ChangeRequestSubject` interface on `Service` model in `app/Modules/Catalog/Domain/Models/Service.php` — `getChangeRequestSubjectType()` returns `ChangeRequestSubjectType::Service`; `getKey()` returns `$this->id`
- [ ] T038 [US2] Create `RequestServiceChangesFormRequest` in `app/Modules/Catalog/Http/Requests/RequestServiceChangesFormRequest.php` — same validation shape as `RequestVendorChangesFormRequest` (items array, field_path, requested_change_en, requested_change_ar); add `@bodyParam` PHPDoc on each field
- [ ] T039 [P] [US2] Create `ServiceResubmitFormRequest` in `app/Modules/Catalog/Http/Requests/ServiceResubmitFormRequest.php` — same validation shape as `VendorResubmitFormRequest`; add `@bodyParam` PHPDoc on each field
- [ ] T040 [US2] Create `RequestServiceChangesAction` in `app/Modules/Catalog/Application/Actions/RequestServiceChangesAction.php` — `execute(Service $service, array $items, User $admin, string $idempotencyKey): ChangeRequest`; same guard and transaction pattern as `RequestVendorChangesAction` but operates on `Service` model and `service.status`; fires `ChangeRequestCreated` via `DB::afterCommit()`
- [ ] T041 [US2] Create `VendorResubmitServiceAfterChangesAction` in `app/Modules/Catalog/Application/Actions/VendorResubmitServiceAfterChangesAction.php` — `execute(Service $service, array $addressedItemIds, array $waivedItemIds, ?string $notes, string $idempotencyKey): ChangeRequest`; guards: assert `service.status === ChangesRequested`, active change request `status=open`; within `DB::transaction`: update items, update change_request `status=resubmitted`, update service `status=pending_review`; fires `ServiceResubmitted` via `DB::afterCommit()`
- [ ] T042 [US2] Create `ServiceChangeRequestController` (admin) in `app/Modules/Catalog/Http/Controllers/ServiceChangeRequestController.php` — `store(RequestServiceChangesFormRequest $request, string $publicId)` — 3-line body
- [ ] T043 [US2] Create `ServiceResubmitController` (vendor) in `app/Modules/Catalog/Http/Controllers/ServiceResubmitController.php` — `store(ServiceResubmitFormRequest $request, string $publicId)` — 3-line body (ownership check via service→vendorProfile→user)
- [ ] T044 [US2] Add `POST /api/v1/admin/services/{publicId}/change-requests` route to `app/Modules/Catalog/Routes/admin.php` — middleware: `auth:sanctum`, `role:admin`, `can:moderate_{product_type}_service`; requires `Idempotency-Key` header check
- [ ] T045 [US2] Add `POST /api/v1/vendor/services/{publicId}/resubmit` route to `app/Modules/Catalog/Routes/vendor.php` — middleware: `auth:sanctum`, `role:vendor`; requires `Idempotency-Key` header check
- [ ] T046 [US2] Add "Request Changes" Filament table action to `RentalServiceResource` in `app/Modules/Catalog/Filament/Resources/RentalServiceResource.php` — same Repeater form pattern as VendorProfileResource; visible when `status === PendingReview` AND no active open change request; delegates to `RequestServiceChangesAction::execute()`
- [ ] T047 [P] [US2] Add "Request Changes" Filament table action to `SaleServiceResource` in `app/Modules/Catalog/Filament/Resources/SaleServiceResource.php` — identical pattern as T046
- [ ] T048 [P] [US2] Add "Request Changes" Filament table action to `DigitalServiceResource` in `app/Modules/Catalog/Filament/Resources/DigitalServiceResource.php` — identical pattern as T046
- [ ] T049 [US2] Create `ServiceResubmitted` domain event in `app/Modules/Catalog/Domain/Events/ServiceResubmitted.php` — constructor: `public Service $service`, `public ChangeRequest $changeRequest`
- [ ] T050 [US2] Create `DispatchServiceResubmittedNotificationListener` in `app/Modules/Catalog/Application/Listeners/DispatchServiceResubmittedNotificationListener.php` — handles `ServiceResubmitted`; calls `DispatchNotificationAction` with event_key `vendor.resubmitted` for the reviewing admin
- [ ] T051 [US2] Register `ServiceResubmitted → DispatchServiceResubmittedNotificationListener` in `app/Modules/Catalog/Providers/CatalogServiceProvider.php`; also register that `ChangeRequestCreated` fires when a service change request is created (reuse existing listener from Identity if cross-module is via event — or wire a separate Catalog-owned listener)

**Checkpoint**: All three product types testable — run `./vendor/bin/pest --filter=ServiceChangesRequestedTest --group=service-changes` and validate all pass.

---

## Phase 5: User Story 3 — System Enforces 3-Cycle Limit (Priority: P3)

**Goal**: 4th change-request attempt is blocked with validation error; admin sees "Force Reject" button when at cycle 3 resubmitted; EscalateAction transitions subject to rejected and writes audit log.

**Independent Test**: Run 3 full cycles on a vendor profile in Tinker or Pest factory helpers → attempt 4th cycle → expect 422 → trigger escalation manually → verify `status = escalated_to_rejection` and vendor profile `approval_status = rejected`.

### Tests for User Story 3

> **Write tests FIRST — confirm they FAIL before writing implementation code**

- [ ] T052 [P] [US3] Add cycle-limit test cases to `VendorChangesRequestedTest.php` in `tests/Feature/Modules/Identity/VendorChangesRequestedTest.php` — new test group `->group('vendor-changes', 'cycle-limit')`: cycle_number increments correctly on each new change request, 4th change-request attempt returns 422 with message "Maximum change cycles reached", escalate endpoint returns 200 with `status=escalated_to_rejection`, vendor profile `approval_status` becomes `rejected` after escalation, audit log entry written on escalation
- [ ] T053 [P] [US3] Add cycle-limit test cases to `ServiceChangesRequestedTest.php` in `tests/Feature/Modules/Catalog/ServiceChangesRequestedTest.php` — same pattern covering all 3 product types, `->group('service-changes', 'cycle-limit')`

### Implementation for User Story 3

- [ ] T054 [US3] Add cycle-limit guard to `RequestVendorChangesAction` in `app/Modules/Identity/Application/Actions/RequestVendorChangesAction.php` — query `MAX(cycle_number)` for subject before creating; throw `ValidationException` with message "Maximum change cycles reached — please approve or reject" if `current_max >= ChangeRequestPolicy::MAX_CYCLES`
- [ ] T055 [P] [US3] Add cycle-limit guard to `RequestServiceChangesAction` in `app/Modules/Catalog/Application/Actions/RequestServiceChangesAction.php` — identical guard logic as T054
- [ ] T056 [US3] Create `EscalateChangeRequestToRejectionAction` in `app/Modules/Shared/Application/Actions/EscalateChangeRequestToRejectionAction.php` — `execute(ChangeRequest $changeRequest, User $admin, ?string $notes): ChangeRequest`; guards: assert `status === Resubmitted` AND `cycle_number === ChangeRequestPolicy::MAX_CYCLES`; within `DB::transaction`: update change_request `status=escalated_to_rejection`, set `resolved_by_admin_id`, `resolved_at`, `resolution_notes`; transition subject (vendor profile → `rejected` / service → `draft` or `archived` per spec intent); write `audit_logs` entry; fire `ChangeRequestEscalated` via `DB::afterCommit()`
- [ ] T057 [US3] Create `ChangeRequestEscalated` domain event in `app/Modules/Shared/Domain/Events/ChangeRequestEscalated.php` — constructor: `public ChangeRequest $changeRequest`, `public User $admin`
- [ ] T058 [US3] Create `ChangeRequestEscalationController` in `app/Modules/Shared/Http/Controllers/ChangeRequestEscalationController.php` — `store(Request $request, string $publicId)` — 3-line body: resolve change request by public_id, call `EscalateChangeRequestToRejectionAction::execute()`, return 200 envelope
- [ ] T059 [US3] Add `POST /api/v1/admin/change-requests/{publicId}/escalate` route in `app/Modules/Shared/Routes/admin.php` — middleware: `auth:sanctum`, `role:admin`; requires `Idempotency-Key` header check; note: if `app/Modules/Shared/Routes/admin.php` does not exist, create it and register in `SharedServiceProvider`
- [ ] T060 [US3] Add "Force Reject (cycle limit)" Filament table action to `VendorProfileResource` in `app/Modules/Identity/Filament/Resources/VendorProfileResource.php` — visible only when active change request has `status=resubmitted` AND `cycle_number === 3`; requires confirmation modal with `escalation_notes` textarea; delegates to `EscalateChangeRequestToRejectionAction::execute()`
- [ ] T061 [P] [US3] Add "Force Reject (cycle limit)" Filament table action to `RentalServiceResource` in `app/Modules/Catalog/Filament/Resources/RentalServiceResource.php` — identical visibility and delegation pattern as T060
- [ ] T062 [P] [US3] Add "Force Reject (cycle limit)" Filament table action to `SaleServiceResource` in `app/Modules/Catalog/Filament/Resources/SaleServiceResource.php` — identical pattern
- [ ] T063 [P] [US3] Add "Force Reject (cycle limit)" Filament table action to `DigitalServiceResource` in `app/Modules/Catalog/Filament/Resources/DigitalServiceResource.php` — identical pattern

**Checkpoint**: All three user stories are independently functional and tested. Run `./vendor/bin/pest --bail` and confirm full suite passes.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Permissions, API documentation, and final validation.

- [ ] T064 Run `php artisan shield:generate --all` to register Filament permissions for new "Request Changes" and "Force Reject" actions — verify in `config/filament-shield.php` that new permission names are correct
- [ ] T065 Update `.specify/memory/api-registry.md` with all 6 new endpoints: `POST /admin/vendor-profiles/{publicId}/change-requests`, `POST /admin/services/{publicId}/change-requests`, `POST /admin/change-requests/{publicId}/escalate`, `GET /vendor/change-requests`, `POST /vendor/vendor-profiles/{publicId}/resubmit`, `POST /vendor/services/{publicId}/resubmit`
- [ ] T066 [P] Add Scribe-compatible `@bodyParam` PHPDoc to `RequestVendorChangesFormRequest`, `VendorResubmitFormRequest`, `RequestServiceChangesFormRequest`, `ServiceResubmitFormRequest` — one PHPDoc block per field as per API documentation constraint
- [ ] T067 [P] Add `@response` PHPDoc with realistic EN+AR example data to `ChangeRequestResource` and `ChangeRequestItemResource` in `app/Modules/Shared/Http/Resources/`
- [ ] T068 Run `php artisan migrate` to apply all 4 migrations in sequence (T007, T008, T018, T036)
- [ ] T069 Run `php artisan db:seed --class=ChangeRequestNotificationTemplateSeeder` to seed `vendor.changes_requested` and `vendor.resubmitted` notification templates
- [ ] T070 Validate end-to-end using `quickstart.md` — complete Steps 1–6: migrate, seed, shield, test full vendor cycle via API, test 3-cycle limit, test in Filament admin

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately. ADR must be `Accepted` before Phase 2.
- **Foundational (Phase 2)**: Depends on ADR acceptance — BLOCKS all user stories. T007 (change_requests migration) must precede T008 (change_request_items).
- **US1 (Phase 3)**: Depends on Phase 2 completion. T017–T019 (enum + migration + interface) must precede T022 (Action). T022 must precede T024 (controller). T029–T030 (events) can be parallel with T022–T025.
- **US2 (Phase 4)**: Depends on Phase 2 completion. Can run in parallel with US1 if two developers are available.
- **US3 (Phase 5)**: T054 modifies `RequestVendorChangesAction` (created in T022) — depends on US1 T022. T055 modifies `RequestServiceChangesAction` (created in T040) — depends on US2 T040. T056 (`EscalateAction`) can start after Phase 2 foundational. T060–T063 (Filament buttons) depend on T056.
- **Polish (Phase 6)**: Depends on all user story phases completing.

### User Story Dependencies

- **US1 (P1)**: Starts after Phase 2 — no dependency on US2 or US3.
- **US2 (P2)**: Starts after Phase 2 — no dependency on US1 (independent in Catalog module), but both use the same ChangeRequest/ChangeRequestItem Shared models.
- **US3 (P3)**: Modifies two Actions from US1 and US2 — must wait for T022 (US1) and T040 (US2). All other US3 tasks (EscalateAction, event, controller, route, Filament buttons) are independent from US1/US2.

### Within Each User Story

1. Write Pest tests (FAIL state) → 2. Enum + migration → 3. Interface on model → 4. Form Requests → 5. Actions → 6. Controllers → 7. Routes → 8. Filament actions → 9. Events + Listeners → 10. ServiceProvider registration → confirm Pest tests PASS.

---

## Parallel Opportunities

```bash
# Phase 2 — all parallel (different files):
T002  # ChangeRequestStatus enum
T003  # ChangeRequestItemStatus enum
T004  # ChangeRequestSubjectType enum
T005  # ChangeRequestSubject interface
T006  # ChangeRequestPolicy constant

# Phase 3 (US1) — form requests parallel:
T020  # RequestVendorChangesFormRequest
T021  # VendorResubmitFormRequest

# Phase 3 (US1) — events parallel after migrations:
T029  # ChangeRequestCreated event
T030  # VendorProfileResubmitted event
T031  # DispatchVendorChangesRequestedNotificationListener
T032  # DispatchVendorResubmittedNotificationListener

# Phase 4 (US2) — three service Resources parallel (different files):
T046  # RentalServiceResource "Request Changes"
T047  # SaleServiceResource "Request Changes"
T048  # DigitalServiceResource "Request Changes"

# Phase 5 (US3) — Filament "Force Reject" buttons parallel:
T060  # VendorProfileResource
T061  # RentalServiceResource
T062  # SaleServiceResource
T063  # DigitalServiceResource
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: ADR-0018
2. Complete Phase 2: Foundational infrastructure
3. Complete Phase 3: US1 — Vendor doc change request workflow
4. **STOP and VALIDATE**: `./vendor/bin/pest --filter=VendorChangesRequestedTest`
5. Demo admin "Request Changes" button + vendor resubmit cycle

### Incremental Delivery

1. Phase 1 + Phase 2 → foundation ready
2. Phase 3 (US1) → vendor onboarding change workflow functional → validate independently
3. Phase 4 (US2) → service moderation change workflow functional (all 3 types) → validate independently
4. Phase 5 (US3) → cycle enforcement active, "Force Reject" button live → validate independently
5. Phase 6 → polished, documented, permissions generated

### Parallel Team Strategy

With two developers after Phase 2:
- Developer A: Phase 3 (US1 — Identity module)
- Developer B: Phase 4 (US2 — Catalog module)
- Both converge on Phase 5 (US3 modifies both modules) then Phase 6

---

## Notes

- T001 (ADR) is a hard gate — no migration can be created before ADR status is `Accepted`
- T007 must precede T008 (foreign key dependency)
- T017 (enum) and T018 (migration) must run before any Action that checks `approval_status`
- T035 (enum) and T036 (migration) must run before any Action that checks `service.status`
- Filament "Request Changes" actions (T028, T046, T047, T048) delegate to Actions — no business logic in Filament closures
- The `ChangeRequestNotificationTemplateSeeder` (T014) must be re-run after `php artisan migrate:fresh` in test environments
- Run `php artisan shield:generate --all` (T064) only AFTER all Filament actions are fully wired — otherwise permissions will be incomplete
