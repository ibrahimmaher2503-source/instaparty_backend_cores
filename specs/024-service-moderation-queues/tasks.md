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

CONSTRAINTS (non-negotiable):
- FR traceability: existing PRD coverage cited from Phase 8.0 and Admin Journey; local FR-EXT items remain until PRD backfill.
- Schema traceability: this feature uses existing `services` lifecycle fields from `11_DB_Schema.md`; current migrations show schema drift that must be corrected.
- Phase alignment: Phase 8.0 - Admin Service Moderation & Publish Workflow.
- No new packages.
- No Phase 2 features.
- No API endpoints.
---

# Tasks: Service Moderation Actions + Per-Type Queues

**Input**: Design documents from `/specs/024-service-moderation-queues/`
**Prerequisites**: `plan.md`, `spec.md`, `research.md`, `data-model.md`, `contracts/admin-ui.md`, `quickstart.md`

**Tests**: Pest coverage is required by the feature spec and quickstart. Write the story tests before implementation and confirm they fail for the missing workflow.

**Organization**: Tasks are grouped by user story so row moderation, pending queues, badges, bulk actions, and material-edit review can be implemented and tested independently after the shared foundation is in place.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel after its prerequisites because it touches a different file or has no dependency on another incomplete task in the same phase.
- **[Story]**: Maps to the user story from `specs/024-service-moderation-queues/spec.md`.
- Every task includes an exact repository path.

## Phase 1: Setup (Shared Checks)

**Purpose**: Confirm the active feature, ADR, and API scope before implementation.

- [x] T001 Confirm `.specify/feature.json` points to `specs/024-service-moderation-queues`.
- [x] T002 Confirm `docs/adr/README.md` registers `docs/adr/ADR-0013-admin-service-moderation.md` as Accepted.
- [x] T003 Confirm no API registry or Bruno artifacts are required for this admin-only Filament feature in `.specify/memory/api-registry.md`.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Align the service lifecycle schema and shared Catalog moderation primitives before any user story work starts.

**Critical**: No user story work can begin until this phase is complete.

- [x] T004 Add a Catalog migration aligning `services.status`, `services.moderation_notes`, `services.moderated_at`, and `services.moderated_by` with the locked schema in `app/Modules/Catalog/Database/Migrations/2026_05_04_000002_align_services_moderation_columns.php`.
- [x] T005 Update lifecycle cases, labels, colors, and transition validation in `app/Modules/Catalog/Domain/Enums/ServiceStatus.php`.
- [x] T006 Update moderation fillable fields, casts, translatable fields, relationships, and pending-review scopes in `app/Modules/Catalog/Domain/Models/Service.php`.
- [x] T007 [P] Add rejected, changes-requested, archived, and moderation-metadata factory states in `app/Modules/Catalog/Database/Factories/ServiceFactory.php`.
- [x] T008 [P] Add the reject lifecycle event class in `app/Modules/Catalog/Domain/Events/ServiceRejected.php`.
- [x] T009 [P] Add the archive lifecycle event class in `app/Modules/Catalog/Domain/Events/ServiceArchived.php`.
- [x] T010 [P] Add the material-edit return lifecycle event class in `app/Modules/Catalog/Domain/Events/ServiceReturnedToReview.php`.
- [x] T011 Add service moderation permissions from ADR-0004 and ADR-0013 in `database/seeders/IdentityRolesSeeder.php`.
- [x] T012 Add service moderation policy methods for approve, reject, request edits, archive, and pending queues in `app/Modules/Catalog/Domain/Policies/ServicePolicy.php`.
- [x] T013 [P] Add English moderation labels, confirmations, validation text, statuses, queue labels, and notifications in `app/Modules/Catalog/Resources/lang/en/catalog.php`.
- [x] T014 [P] Add Arabic moderation labels, confirmations, validation text, statuses, queue labels, and notifications in `app/Modules/Catalog/Resources/lang/ar/catalog.php`.

**Checkpoint**: Schema, enum, model, permissions, events, factories, and translations are ready for story implementation.

---

## Phase 3: User Story 1 - Moderate One Pending Service (Priority: P1) MVP

**Goal**: Admins can approve and publish, reject with EN+AR notes, or request edits on a single pending rental, sale, or digital service from row actions.

**Independent Test**: Create pending services for all three product types, run each row decision, and verify status, moderator metadata, notes/change-request state, action visibility, and lifecycle events.

### Tests for User Story 1

- [x] T015 [P] [US1] Add failing Pest coverage for row approve, reject, bilingual validation, request-edits delegation, action visibility, and after-commit events in `tests/Feature/Modules/Catalog/ServiceModerationActionTest.php`.

### Implementation for User Story 1

- [x] T016 [US1] Implement transactional publish workflow and after-commit `ServicePublished` dispatch in `app/Modules/Catalog/Application/Actions/PublishServiceAction.php`.
- [x] T017 [US1] Implement transactional reject workflow, EN+AR note validation, moderator metadata, and after-commit `ServiceRejected` dispatch in `app/Modules/Catalog/Application/Actions/RejectServiceAction.php`.
- [x] T018 [US1] Create the Filament approve row-action adapter in `app/Modules/Catalog/Filament/Actions/ApproveServiceAction.php`.
- [x] T019 [US1] Create the Filament reject row-action adapter with bilingual form fields in `app/Modules/Catalog/Filament/Actions/RejectServiceAction.php`.
- [x] T020 [US1] Create the Filament request-edits row-action adapter delegating to existing per-type change-request actions in `app/Modules/Catalog/Filament/Actions/RequestServiceEditsAction.php`.
- [x] T021 [US1] Attach row moderation actions and restrict manual status editing in `app/Modules/Catalog/Filament/Resources/RentalServiceResource.php`.
- [x] T022 [US1] Attach row moderation actions and restrict manual status editing in `app/Modules/Catalog/Filament/Resources/SaleServiceResource.php`.
- [x] T023 [US1] Attach row moderation actions and restrict manual status editing in `app/Modules/Catalog/Filament/Resources/DigitalServiceResource.php`.

**Checkpoint**: User Story 1 is complete when row moderation works independently for rental, sale, and digital services.

---

## Phase 4: User Story 5 - Re-Moderate Material Edits (Priority: P1)

**Goal**: Published services return to `pending_review` when customer-facing material fields or core media change, while non-material edits remain published.

**Independent Test**: Publish one service of each product type, edit material fields and media, verify each returns to `pending_review`; edit a non-material field and verify it stays `published`.

### Tests for User Story 5

- [x] T024 [P] [US5] Add failing Pest coverage for rental, sale, and digital material and non-material edits in `tests/Feature/Modules/Catalog/ServiceMaterialEditGateTest.php`.

### Implementation for User Story 5

- [x] T025 [US5] Implement the material edit detector and pending-review transition in `app/Modules/Catalog/Application/Actions/MarkServicePendingReviewForMaterialEditAction.php`.
- [x] T026 [US5] Wire material-edit detection into rental edit save handling in `app/Modules/Catalog/Filament/Resources/RentalServiceResource/Pages/EditRentalService.php`.
- [x] T027 [US5] Wire material-edit detection into sale edit save handling in `app/Modules/Catalog/Filament/Resources/SaleServiceResource/Pages/EditSaleService.php`.
- [x] T028 [US5] Wire material-edit detection into digital edit save handling in `app/Modules/Catalog/Filament/Resources/DigitalServiceResource/Pages/EditDigitalService.php`.

**Checkpoint**: User Story 5 is complete when all three product types return to review only for material edits.

---

## Phase 5: User Story 2 - Work Dedicated Pending Queues Per Product Type (Priority: P1)

**Goal**: Admins can open dedicated pending-review queue pages for rental, sale, and digital services with the pending status scope baked into the page query.

**Independent Test**: Seed pending, published, rejected, archived, and cross-type services; verify each pending queue shows only its own product type in `pending_review` and has no removable status filter exposing other statuses.

### Tests for User Story 2

- [x] T029 [P] [US2] Add failing Pest coverage for per-type queue scoping, hidden status filter, empty state text, and type/status badges in `tests/Feature/Modules/Catalog/ServiceModerationQueueTest.php`.

### Implementation for User Story 2

- [x] T030 [P] [US2] Create the baked-in rental pending queue page in `app/Modules/Catalog/Filament/Resources/RentalServiceResource/Pages/PendingRentalServicesPage.php`.
- [x] T031 [P] [US2] Create the baked-in sale pending queue page in `app/Modules/Catalog/Filament/Resources/SaleServiceResource/Pages/PendingSaleServicesPage.php`.
- [x] T032 [P] [US2] Create the baked-in digital pending queue page in `app/Modules/Catalog/Filament/Resources/DigitalServiceResource/Pages/PendingDigitalServicesPage.php`.
- [x] T033 [US2] Register rental pending queue routes and navigation metadata in `app/Modules/Catalog/Filament/Resources/RentalServiceResource.php`.
- [x] T034 [US2] Register sale pending queue routes and navigation metadata in `app/Modules/Catalog/Filament/Resources/SaleServiceResource.php`.
- [x] T035 [US2] Register digital pending queue routes and navigation metadata in `app/Modules/Catalog/Filament/Resources/DigitalServiceResource.php`.

**Checkpoint**: User Story 2 is complete when each queue independently shows only pending services for its product type.

---

## Phase 6: User Story 3 - See Pending Backlog Counts in Navigation (Priority: P2)

**Goal**: The admin sidebar shows pending-review counts per service type using the existing navigation badge convention.

**Independent Test**: Seed known pending counts per product type and verify the rental, sale, and digital service navigation badges match those counts before and after moderation decisions.

### Tests for User Story 3

- [x] T036 [P] [US3] Add failing Pest coverage for pending-review navigation badge counts and count updates after transitions in `tests/Feature/Modules/Catalog/ServiceModerationBadgeTest.php`.

### Implementation for User Story 3

- [x] T037 [US3] Add pending-review navigation badge count and badge color to `app/Modules/Catalog/Filament/Resources/RentalServiceResource.php`.
- [x] T038 [US3] Add pending-review navigation badge count and badge color to `app/Modules/Catalog/Filament/Resources/SaleServiceResource.php`.
- [x] T039 [US3] Add pending-review navigation badge count and badge color to `app/Modules/Catalog/Filament/Resources/DigitalServiceResource.php`.

**Checkpoint**: User Story 3 is complete when badges match true pending counts for all three product types.

---

## Phase 7: User Story 4 - Apply Bulk Moderation Decisions (Priority: P2)

**Goal**: Admins can approve, reject with shared EN+AR notes, or archive selected services in bulk while preserving row-action semantics.

**Independent Test**: Select 50 pending services for one product type and approve them in one operation; verify all selected rows publish with moderator metadata and conflict behavior is explicit.

### Tests for User Story 4

- [x] T040 [P] [US4] Add failing Pest coverage for 50-row bulk approve, shared bilingual bulk reject, archive selected, and conflict handling in `tests/Feature/Modules/Catalog/ServiceModerationBulkActionTest.php`.

### Implementation for User Story 4

- [x] T041 [US4] Implement transactional archive workflow and after-commit `ServiceArchived` dispatch in `app/Modules/Catalog/Application/Actions/ArchiveServiceAction.php`.
- [x] T042 [US4] Create the reusable Filament bulk approve adapter in `app/Modules/Catalog/Filament/Actions/BulkApproveServicesAction.php`.
- [x] T043 [US4] Create the reusable Filament bulk reject adapter with shared bilingual form fields in `app/Modules/Catalog/Filament/Actions/BulkRejectServicesAction.php`.
- [x] T044 [US4] Create the reusable Filament bulk archive adapter in `app/Modules/Catalog/Filament/Actions/BulkArchiveServicesAction.php`.
- [x] T045 [US4] Attach approve, reject, and archive bulk actions to `app/Modules/Catalog/Filament/Resources/RentalServiceResource.php`.
- [x] T046 [US4] Attach approve, reject, and archive bulk actions to `app/Modules/Catalog/Filament/Resources/SaleServiceResource.php`.
- [x] T047 [US4] Attach approve, reject, and archive bulk actions to `app/Modules/Catalog/Filament/Resources/DigitalServiceResource.php`.

**Checkpoint**: User Story 4 is complete when bulk actions preserve row semantics across all three resources.

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Final verification, documentation cleanup, and quality gates.

- [x] T048 [P] Update final implementation notes and any discovered schema drift decisions in `specs/024-service-moderation-queues/quickstart.md`.
- [x] T049 Run `./vendor/bin/pint` as required by `specs/024-service-moderation-queues/quickstart.md`.
- [ ] T050 Run `./vendor/bin/phpstan analyse` as required by `specs/024-service-moderation-queues/quickstart.md`.
- [ ] T051 Run `./vendor/bin/pest --filter=ServiceModeration --bail` as required by `specs/024-service-moderation-queues/quickstart.md`.
- [ ] T052 Run `./vendor/bin/pest --bail` as required by `specs/024-service-moderation-queues/quickstart.md`.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies.
- **Foundational (Phase 2)**: Depends on Setup and blocks every user story.
- **User Story 1 (Phase 3, P1)**: Depends on Foundational.
- **User Story 5 (Phase 4, P1)**: Depends on Foundational and should run before final queue verification because material edits feed pending queues.
- **User Story 2 (Phase 5, P1)**: Depends on Foundational; can be developed alongside US1/US5 after shared primitives exist, then verified with their status transitions.
- **User Story 3 (Phase 6, P2)**: Depends on queue/resource scopes from US2 and transition behavior from US1/US5.
- **User Story 4 (Phase 7, P2)**: Depends on row-level publish/reject actions from US1 and shared archive action implementation.
- **Polish (Phase 8)**: Depends on all selected user stories.

### User Story Dependencies

- **US1 Moderate One Pending Service**: Core MVP; no dependency on other stories after Foundation.
- **US5 Re-Moderate Material Edits**: P1; no dependency on queue UI after Foundation, but queue tests should later assert returned services appear.
- **US2 Dedicated Pending Queues**: P1; no dependency on badge or bulk behavior after Foundation.
- **US3 Navigation Badges**: P2; depends on the same per-type pending scopes used by US2.
- **US4 Bulk Moderation Decisions**: P2; reuses US1 publish/reject actions and adds archive semantics.

### Within Each User Story

- Write Pest tests first and confirm they fail for the missing workflow.
- Keep mutation logic in `app/Modules/Catalog/Application/Actions/`.
- Keep Filament action classes as UI adapters that delegate to application actions.
- Update each resource type explicitly; do not replace the three-resource design with one polymorphic resource.
- Use `ProductType` enum and `match` for type-aware branching; do not branch on product type strings with `if`/`elseif`.

---

## Parallel Opportunities

- T007, T008, T009, T010, T013, and T014 can run in parallel after T004-T006 are understood because they touch independent files.
- T018, T019, and T020 can be implemented in parallel after T016-T017 define the application action contracts.
- T021, T022, and T023 can be implemented in parallel across the three resource files after the Filament adapters exist.
- T026, T027, and T028 can be implemented in parallel across the three edit page files after T025 exists.
- T030, T031, and T032 can be implemented in parallel across the three pending queue page files.
- T033, T034, and T035 can be implemented in parallel across the three resource files after their pending queue pages exist.
- T037, T038, and T039 can be implemented in parallel across the three resource files after US2 establishes the count scope.
- T045, T046, and T047 can be implemented in parallel across the three resource files after T042-T044 exist.

## Parallel Example: User Story 2

```bash
# Queue page creation can be split by product type:
Task: "T030 [P] [US2] Create the baked-in rental pending queue page in app/Modules/Catalog/Filament/Resources/RentalServiceResource/Pages/PendingRentalServicesPage.php"
Task: "T031 [P] [US2] Create the baked-in sale pending queue page in app/Modules/Catalog/Filament/Resources/SaleServiceResource/Pages/PendingSaleServicesPage.php"
Task: "T032 [P] [US2] Create the baked-in digital pending queue page in app/Modules/Catalog/Filament/Resources/DigitalServiceResource/Pages/PendingDigitalServicesPage.php"
```

## Parallel Example: User Story 4

```bash
# Resource bulk action attachment can be split by product type:
Task: "T045 [US4] Attach approve, reject, and archive bulk actions to app/Modules/Catalog/Filament/Resources/RentalServiceResource.php"
Task: "T046 [US4] Attach approve, reject, and archive bulk actions to app/Modules/Catalog/Filament/Resources/SaleServiceResource.php"
Task: "T047 [US4] Attach approve, reject, and archive bulk actions to app/Modules/Catalog/Filament/Resources/DigitalServiceResource.php"
```

---

## Implementation Strategy

### MVP First

1. Complete Phase 1 and Phase 2.
2. Complete Phase 3 (US1 row moderation).
3. Validate `tests/Feature/Modules/Catalog/ServiceModerationActionTest.php`.
4. Continue with the remaining P1 stories before considering Phase 8.0 exit complete.

### P1 Completion

1. Add US5 material-edit re-review so published service edits cannot bypass moderation.
2. Add US2 dedicated pending queues so admins can work pending services by product type.
3. Run the targeted ServiceModeration Pest filter before adding P2 work.

### Incremental Delivery

1. Foundation -> US1 row actions -> targeted tests.
2. US5 material edit gate -> targeted tests.
3. US2 pending queues -> targeted tests.
4. US3 navigation badges -> targeted tests.
5. US4 bulk actions -> targeted tests.
6. Full quality commands from `specs/024-service-moderation-queues/quickstart.md`.

## Notes

- No new packages are allowed for this feature.
- No public, vendor, customer, or internal HTTP endpoints are added, so API registry and Bruno collection updates are not required.
- Keep all implementation in the Catalog module except the existing role seeder path.
- Mark each completed task as `[x]` in this file immediately after completion.
