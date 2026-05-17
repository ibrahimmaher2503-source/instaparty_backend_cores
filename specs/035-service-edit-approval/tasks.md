---
description: "Tasks for Service Material Edit Approval Workflow (035-service-edit-approval)"
---

# Tasks: Service Material Edit Approval Workflow

**Feature**: `035-service-edit-approval`
**Input**: Design documents in `/specs/035-service-edit-approval/`
**Prerequisites**: plan.md ✓, spec.md ✓, research.md ✓, data-model.md ✓, contracts/ ✓, quickstart.md ✓

**Tests**: Pest tests are **required** per the original feature request and spec FR-EXT-013/SC-005 (every product type must be covered).

**Organization**: Phases below are gated. Setup → Foundational → US1 → US2 → US3 → US4 → US5 → Polish. P1 stories (US1–US3) form the MVP; US4 and US5 may ship in the same release or follow-up.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Parallelizable — touches a distinct file and has no dependency on incomplete tasks in the same phase.
- **[Story]**: Maps to user stories from spec.md (US1, US2, US3, US4, US5). Setup / Foundational / Polish tasks have no story label.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Documentation, ADR, and migrations — anything that must land before code.

- [ ] T001 Write and ratify `docs/adr/ADR-0035-service-material-edit-approval.md` documenting the staged-edits decision per `research.md` R1 (context, decision, status=Proposed, consequences, deprecation note for `MarkServicePendingReviewForMaterialEditAction`)
- [ ] T002 [P] Create migration `app/Modules/Catalog/Database/Migrations/2026_05_16_000010_create_service_change_requests_table.php` matching `data-model.md` Table 1 (incl. `open_lock_key` generated column + UNIQUE index, `version` column, status ENUM, FKs with `restrictOnDelete()`/`SET NULL`)
- [ ] T003 [P] Create migration `app/Modules/Catalog/Database/Migrations/2026_05_16_000011_create_service_change_request_items_table.php` matching `data-model.md` Table 2
- [ ] T004 [P] Create migration `app/Modules/Catalog/Database/Migrations/2026_05_16_000012_create_service_change_request_messages_table.php` matching `data-model.md` Table 3 (append-only, no `updated_at`)
- [ ] T005 Run `php artisan migrate` and verify with `SHOW INDEX FROM service_change_requests` that the partial-unique `open_lock_key` index is present and FKs are wired
- [ ] T006 [P] Update `.specify/memory/api-registry.md` with the new admin endpoints and the modified `PUT /api/v1/vendor/services/{publicId}` behavior per `contracts/admin-decide.md` and `contracts/vendor-submit-edit.md`

---

## Phase 2: Foundational (blocking prerequisites)

**Purpose**: Domain-layer building blocks that every user story depends on. Must complete before Phase 3.

- [ ] T007 [P] Create enum `app/Modules/Catalog/Domain/Enums/ServiceChangeRequestStatus.php` (cases: `Pending`, `AwaitingClarification`, `Approved`, `Rejected`, `CancelledVendorSuspended`, `CancelledServiceUnavailable`) with `label()` returning bilingual translation keys
- [ ] T008 [P] Create enum `app/Modules/Catalog/Domain/Enums/ServiceFieldClassification.php` (cases: `Shared`, `Rental`, `Sale`, `Digital`, `Media`, `Availability`, `PricingTier`) with `badgeColor()` per `quickstart.md` §6 color map
- [ ] T009 Create policy `app/Modules/Catalog/Domain/Policies/MaterialFieldRegistry.php` with exhaustive arrays per `ProductType` from `research.md` R2; public method `materialFieldsFor(ProductType $type): array` using `match($type)`
- [ ] T010 [P] Create policy `app/Modules/Catalog/Domain/Policies/ServiceEditApprovalPolicy.php` with constants `MAX_CLARIFICATIONS = 3`
- [ ] T011 Create model `app/Modules/Catalog/Domain/Models/ServiceChangeRequest.php` with relations (`service`, `vendorProfile`, `submittedBy`, `decidedBy`, `items`, `messages`), translatable `vendor_note` + `admin_note`, casts (`proposed_changes:array`, `before_snapshot:array`, `status` enum), `hasOpenChangeRequest()` scope, `incrementVersion()` helper
- [ ] T012 [P] Create model `app/Modules/Catalog/Domain/Models/ServiceChangeRequestItem.php` (relations to parent + service; `before_value`/`after_value` cast to array)
- [ ] T013 [P] Create model `app/Modules/Catalog/Domain/Models/ServiceChangeRequestMessage.php` with append-only enforcement (override `save()`/`update()` to throw if record exists; translatable `body`)
- [ ] T014 [P] Add scope to `app/Modules/Catalog/Domain/Models/Service.php`: `hasOpenChangeRequest()` returning `bool`; relation `changeRequests(): HasMany`
- [ ] T015 [P] Create DTO `app/Modules/Catalog/Application/DTOs/ServiceFieldDiff.php` (readonly props: `items` list, `productType`, helpers `isEmpty()`, `hasMaterialChanges()`)
- [ ] T016 [P] Create DTO `app/Modules/Catalog/Application/DTOs/SubmitServiceChangeRequestDTO.php` (service id, vendor user, proposed payload, vendor note, idempotency key)
- [ ] T017 [P] Create DTO `app/Modules/Catalog/Application/DTOs/DecideServiceChangeRequestDTO.php` (admin user, admin note bilingual, version)
- [ ] T018 [P] Create event `app/Modules/Catalog/Domain/Events/ServiceChangeRequestSubmitted.php`
- [ ] T019 [P] Create event `app/Modules/Catalog/Domain/Events/ServiceChangeRequestApproved.php` (carries `array $appliedFieldPaths`)
- [ ] T020 [P] Create event `app/Modules/Catalog/Domain/Events/ServiceChangeRequestRejected.php`
- [ ] T021 [P] Create event `app/Modules/Catalog/Domain/Events/ServiceChangeRequestClarificationRequested.php` (carries the new `ServiceChangeRequestMessage`)
- [ ] T022 [P] Create event `app/Modules/Catalog/Domain/Events/ServiceChangeRequestClarificationReplied.php`
- [ ] T023 [P] Create factory `app/Modules/Catalog/Database/Factories/ServiceChangeRequestFactory.php` (states: `pending`, `awaitingClarification`, `approved`, `rejected`)
- [ ] T024 [P] Create factory `app/Modules/Catalog/Database/Factories/ServiceChangeRequestItemFactory.php`
- [ ] T025 [P] Create factory `app/Modules/Catalog/Database/Factories/ServiceChangeRequestMessageFactory.php`
- [ ] T026 Wire all five events to subscribers in `app/Modules/Catalog/Providers/CatalogServiceProvider.php::boot()` (listener stubs created later in each story phase)
- [ ] T027 [P] Add translation keys to `app/Modules/Catalog/Resources/lang/en/service_change_request.php` and `.../ar/service_change_request.php` (status labels, validation messages, notification subjects)

**Gate**: Phase 2 complete when all models load via `ServiceChangeRequest::factory()->make()`, the partial unique index rejects two `pending` rows on the same service, and `MaterialFieldRegistry::materialFieldsFor(ProductType::Rental)` returns the full array from `research.md` R2.

---

## Phase 3: User Story 1 — Vendor Submits a Material Edit (P1) {#us1}

**Story goal**: Vendor on a `published` service who saves a material change creates a `service_change_request`; the live row stays unchanged; discovery API still returns the previous values.

**Independent test**: Per `spec.md` US1 — for each `ProductType`, publish a service, submit a material edit, assert (a) live row byte-identical, (b) one `service_change_request` row with `status=pending`, (c) public discovery returns old values, (d) second material edit returns HTTP 409.

### Implementation (US1)

- [ ] T028 [US1] Create `app/Modules/Catalog/Application/Actions/DetectMaterialServiceChangesAction.php` — pure function; `execute(Service $service, array $proposedPayload): ServiceFieldDiff`. Uses `MaterialFieldRegistry` and `match(ProductType)`. No DB writes.
- [ ] T029 [US1] Create `app/Modules/Catalog/Application/Actions/SubmitServiceChangeRequestAction.php` — `execute(SubmitServiceChangeRequestDTO): ServiceChangeRequest`. Inside `DB::transaction`: `lockForUpdate` on service, re-check via `ServiceChangeRequest::query()->where('service_id', ...)->whereIn('status', ['pending','awaiting_clarification'])->lockForUpdate()->exists()` and throw 409 if true, build `before_snapshot` from current service + detail + media + availability + pricing tiers, insert parent + N items in one transaction, fire `ServiceChangeRequestSubmitted` via `DB::afterCommit`.
- [ ] T030 [US1] Modify `app/Modules/Catalog/Http/Controllers/Vendor/VendorRentalServiceController.php::update()` to branch: `DetectMaterialServiceChangesAction` → if material on `published` service, call `SubmitServiceChangeRequestAction` and return `ServiceChangeRequestResource` with HTTP 202; else call existing direct-apply path
- [ ] T031 [P] [US1] Same modification in `app/Modules/Catalog/Http/Controllers/Vendor/VendorSaleServiceController.php::update()`
- [ ] T032 [P] [US1] Same modification in `app/Modules/Catalog/Http/Controllers/Vendor/VendorDigitalServiceController.php::update()`
- [ ] T033 [P] [US1] Create `app/Modules/Catalog/Http/Resources/ServiceChangeRequestResource.php` returning the shape documented in `contracts/admin-decide.md` (data/meta/errors envelope, items array, messages thread, version metadata) with `@response` Scribe annotation including EN+AR example
- [ ] T034 [P] [US1] Create `app/Modules/Catalog/Http/Resources/ServiceChangeRequestItemResource.php`
- [ ] T035 [US1] Annotate `app/Modules/Catalog/Application/Actions/MarkServicePendingReviewForMaterialEditAction.php` with `@deprecated 035-service-edit-approval — for published services use SubmitServiceChangeRequestAction` and short-circuit when `$service->status === ServiceStatus::Published` (return service unchanged) so legacy callers no longer dark-window listings
- [ ] T036 [P] [US1] Add audit listener stub `app/Modules/Catalog/Application/Listeners/WriteServiceChangeRequestAuditListener.php` — handle `ServiceChangeRequestSubmitted` first (other events added in later stories); writes one `audit_logs` row per submission

### Bruno + registry (US1)

- [ ] T037 [P] [US1] Add Bruno collection entry `docs/api/collections/vendor/services/update-service-staged-202.bru` covering Branch C (material edit, 202 Accepted) from `contracts/vendor-submit-edit.md`
- [ ] T038 [P] [US1] Add Bruno collection entry `docs/api/collections/vendor/services/update-service-pending-conflict.bru` covering Branch D (409 duplicate)

### Tests (US1)

- [X] T039 [P] [US1] `tests/Feature/Modules/Catalog/ServiceEditApproval/MaterialFieldRegistryTest.php` — 26 tests across all 3 product types; classifyField, isMaterial, materialFieldsFor coverage ✓
- [X] T040 [P] [US1] `tests/Feature/Modules/Catalog/ServiceEditApproval/SubmitMaterialEditTest.php` — rental/sale/digital submit + live row safety + duplicate 409 + wrong vendor 404 + unauth 401 + draft 501 ✓
- [X] T041 [P] [US1] covered in SubmitMaterialEditTest: published service live row is never touched by material edit submission (3 product types) ✓
- [X] T042 [P] [US1] covered in SubmitMaterialEditTest: second material edit on same service while pending → 409 ✓
- [X] T043 [P] [US1] covered in SubmitMaterialEditTest: vendor cannot submit edit on another vendor's service → 404 ✓

**US1 checkpoint**: Vendors can stage edits, live service stays up, duplicates blocked, all three types covered.

---

## Phase 4: User Story 2 — Admin Approves a Staged Edit (P1) {#us2}

**Story goal**: Admin opens `PendingServiceEditsPage`, sees the diff, clicks Approve; live service + detail row updated atomically; Scout re-indexes; vendor notified; audit log written per field.

**Independent test**: Per `spec.md` US2 — for each `ProductType`, create a `pending` CR, approve as admin with `service.moderate.{type}` permission, assert live values match proposed, audit rows present, event fired, Scout queue dispatched.

### Implementation (US2)

- [ ] T044 [US2] Create `app/Modules/Catalog/Application/Actions/ApplyServiceChangeToLiveAction.php` — private helper Action; `execute(ServiceChangeRequest): array` returns list of applied field paths. Uses `match(ProductType)` to dispatch into `applyRental`, `applySale`, `applyDigital`. Replays media gallery ops, availability windows, pricing tiers in deterministic order inside parent transaction.
- [ ] T045 [US2] Create `app/Modules/Catalog/Application/Actions/ApproveServiceChangeRequestAction.php` — `execute(ServiceChangeRequest $cr, DecideServiceChangeRequestDTO $dto): ServiceChangeRequest`. Inside `DB::transaction`: version-check UPDATE (throw 409 on mismatch), call `ApplyServiceChangeToLiveAction`, mutate decision columns, fire `ServiceChangeRequestApproved` with applied paths via `DB::afterCommit`
- [ ] T046 [US2] Create `app/Modules/Catalog/Http/Requests/Admin/ApproveServiceChangeRequest.php` with `authorize()` calling `Gate::authorize('service.moderate.' . $service->product_type->value, $service)` and validation for optional bilingual `admin_note.en`/`.ar` + required `version` integer
- [ ] T047 [US2] Create `app/Modules/Catalog/Http/Controllers/Admin/AdminServiceChangeRequestController.php::approve()` — 3-line body delegating to `ApproveServiceChangeRequestAction`; returns `ServiceChangeRequestResource`
- [ ] T048 [US2] Register admin route `POST /admin/service-change-requests/{publicId}/approve` in `app/Modules/Catalog/Routes/admin.php` with auth middleware
- [ ] T049 [US2] Create Filament page `app/Modules/Catalog/Filament/Pages/PendingServiceEditsPage.php` under navigation group `Services` with table eager-loading `service`, `vendor`, `items`; columns: product type badge (per `filament-components.md` §2 color map), vendor display name, field count, submitted age, status badge; filters: `product_type` SelectFilter, `vendor_profile_id`, `status`
- [ ] T050 [P] [US2] Add diff modal view `resources/views/filament/catalog/pages/diff.blade.php` rendering side-by-side before/after columns grouped by `field_classification` with per-classification color badges
- [ ] T051 [US2] Add `Approve` row Action button to the Filament page using `Action::make('approve')`; form contains optional bilingual `admin_note`; on submit delegates to `ApproveServiceChangeRequestAction` and renders Filament `Notification::make()->success()` on success or `->danger()` on 409 version conflict
- [ ] T052 [P] [US2] Extend `WriteServiceChangeRequestAuditListener` to handle `ServiceChangeRequestApproved` — writes one `audit_logs` row per element of `$appliedFieldPaths`
- [ ] T053 [P] [US2] Create `app/Modules/Communication/Application/Listeners/DispatchServiceChangeApprovedNotificationListener.php` (queued) using template `service.change_request.approved`
- [ ] T054 [P] [US2] Add bilingual notification template seeder entry for `service.change_request.approved` with EN+AR subject and body (push + email channels) — `app/Modules/Communication/Database/Seeders/ServiceChangeRequestTemplatesSeeder.php`
- [ ] T055 [P] [US2] Add Filament nav-badge widget `app/Modules/Catalog/Filament/Widgets/PendingServiceEditsBadgeWidget.php` returning count of `pending` CRs; register on the page navigation
- [ ] T056 [US2] Run `php artisan shield:generate --all` and commit generated permission entries (no new permission strings; this regenerates the Filament Shield manifest for the new page)

### Bruno + registry (US2)

- [ ] T057 [P] [US2] Add Bruno entry `docs/api/collections/admin/service-change-requests/approve.bru`

### Tests (US2)

- [X] T058 [P] [US2] `tests/Feature/Modules/Catalog/ServiceEditApproval/ApproveServiceEditTest.php` — rental/sale/digital approve + bilingual note + version conflict 409 + already-approved 409 + unauth 401 + vendor cannot approve 403 ✓
- [X] T059 [P] [US2] covered in ApproveServiceEditTest: vendor cannot approve their own CR → 403 ✓
- [X] T060 [P] [US2] covered in ApproveServiceEditTest: stale version number returns 409 conflict ✓
- [ ] T061 [P] [US2] `tests/Feature/Modules/Catalog/ServiceChangeRequest/MissingCategoryApprovalTest.php` — proposed `category_id` no longer exists; approval rolls back, CR stays `pending`, live row unchanged

**US2 checkpoint**: Admin can approve from Filament page; atomic apply works for all three types; permissions enforced; concurrent clicks safe.

---

## Phase 5: User Story 3 — Admin Rejects With Bilingual Reason (P1) {#us3}

**Story goal**: Admin clicks Reject with EN+AR reason; live service untouched; vendor sees reason and can submit a fresh edit; audit row recorded.

**Independent test**: Per `spec.md` US3 — submit `pending` CR, reject as admin with bilingual reason, assert live row byte-identical, CR `status=rejected` with both notes persisted, vendor regains ability to submit a new edit.

### Implementation (US3)

- [ ] T062 [US3] Create `app/Modules/Catalog/Application/Actions/RejectServiceChangeRequestAction.php` — `execute(ServiceChangeRequest, DecideServiceChangeRequestDTO): ServiceChangeRequest`. Version-check, set `status=rejected`, persist bilingual `admin_note`, fire `ServiceChangeRequestRejected` via `DB::afterCommit`. Live row untouched (no call to `ApplyServiceChangeToLiveAction`).
- [ ] T063 [US3] Create `app/Modules/Catalog/Http/Requests/Admin/RejectServiceChangeRequest.php` requiring BOTH `admin_note.en` and `admin_note.ar` (each `string|max:4000`); `authorize()` uses per-type `service.moderate.{type}` gate
- [ ] T064 [US3] Add `AdminServiceChangeRequestController::reject()` (3-line body) and register `POST /admin/service-change-requests/{publicId}/reject` route
- [ ] T065 [US3] Add `Reject` row Action button to `PendingServiceEditsPage` with form requiring both EN and AR `admin_note` (Filament `->required()` on each tab)
- [ ] T066 [P] [US3] Extend `WriteServiceChangeRequestAuditListener` to handle `ServiceChangeRequestRejected` — one row capturing actor, reason hash
- [ ] T067 [P] [US3] Create `app/Modules/Communication/Application/Listeners/DispatchServiceChangeRejectedNotificationListener.php` (queued) using template `service.change_request.rejected`
- [ ] T068 [P] [US3] Add template seeder entry `service.change_request.rejected` (EN+AR, push + email)

### Bruno + vendor UI (US3)

- [ ] T069 [P] [US3] Add Bruno entry `docs/api/collections/admin/service-change-requests/reject.bru`
- [ ] T070 [P] [US3] Add vendor portal contract note — the existing `GET /api/v1/vendor/services/{publicId}` Resource already exposes `change_requests` relation via the `ServiceChangeRequestResource`; verify the rejected CR's `admin_note` (bilingual) is included in the eager-loaded payload so the vendor portal can display it inline (no new endpoint needed)

### Tests (US3)

- [X] T071 [P] [US3] `tests/Feature/Modules/Catalog/ServiceEditApproval/RejectServiceEditTest.php` — rental/sale/digital reject + bilingual note + Arabic-only 422 + English-only 422 + no-note 422 + terminal CR 409 + vendor 403 ✓
- [X] T072 [P] [US3] covered in RejectServiceEditTest: rejection without Arabic admin note returns 422 (assertJsonValidationErrors) ✓
- [ ] T073 [P] [US3] `tests/Feature/Modules/Catalog/ServiceChangeRequest/RejectThenResubmitTest.php` — after rejection, vendor submits a fresh material edit on the same service → succeeds with new CR row; rejected CR row preserved untouched

**US3 checkpoint**: Reject path is fully wired; bilingual validation enforced; vendor can retry.

---

## Phase 6: User Story 4 — Admin Requests Clarification (P2) {#us4}

**Story goal**: Admin asks a question without rejecting; CR enters `awaiting_clarification`; vendor sees the question and can reply; cycle cap of 3 is enforced.

**Independent test**: Per `spec.md` US4 — request clarification with bilingual question, assert status flow, vendor replies, status returns to `pending`, 4th clarification round blocked.

### Implementation (US4)

- [ ] T074 [US4] Create `app/Modules/Catalog/Application/Actions/RequestServiceChangeClarificationAction.php` — version-check, abort if `clarification_round >= 3`, set status, increment counter, persist admin question on parent row, append `ServiceChangeRequestMessage` with `author_role=admin`, fire `ServiceChangeRequestClarificationRequested` after commit
- [ ] T075 [US4] Create `app/Modules/Catalog/Application/Actions/ReplyToServiceChangeClarificationAction.php` — vendor-side; verify vendor owns the CR, append `ServiceChangeRequestMessage` with `author_role=vendor`, set status back to `pending`, fire `ServiceChangeRequestClarificationReplied` after commit
- [ ] T076 [US4] Create `app/Modules/Catalog/Http/Requests/Admin/RequestServiceChangeClarificationRequest.php` requiring bilingual `admin_note`; `authorize()` checks gate + round cap
- [ ] T077 [US4] Create `app/Modules/Catalog/Http/Requests/Vendor/ReplyServiceChangeClarificationRequest.php` requiring bilingual `body`
- [ ] T078 [US4] Add `AdminServiceChangeRequestController::requestClarification()` and `VendorServiceChangeRequestController::reply()` (3-line bodies), register routes `POST /admin/service-change-requests/{publicId}/request-clarification` and `POST /api/v1/vendor/service-change-requests/{publicId}/reply`
- [ ] T079 [US4] Add `Request Clarification` row Action button to `PendingServiceEditsPage` (form with bilingual question; hidden when `clarification_round >= 3` with disabled tooltip)
- [ ] T080 [P] [US4] Extend `WriteServiceChangeRequestAuditListener` to handle `ServiceChangeRequestClarificationRequested` and `ServiceChangeRequestClarificationReplied`
- [ ] T081 [P] [US4] Create `app/Modules/Communication/Application/Listeners/DispatchServiceChangeClarificationNotificationListener.php` (queued) using template `service.change_request.clarification_requested`
- [ ] T082 [P] [US4] Add template seeder entry `service.change_request.clarification_requested` (EN+AR, push + email)

### Bruno (US4)

- [ ] T083 [P] [US4] Add Bruno entries `docs/api/collections/admin/service-change-requests/request-clarification.bru` and `docs/api/collections/vendor/service-change-requests/reply.bru`

### Tests (US4)

- [X] T084 [P] [US4] `tests/Feature/Modules/Catalog/ServiceEditApproval/ClarificationFlowTest.php` — rental/sale/digital clarification request + vendor reply + round increment + Arabic-only 422 + non-awaiting 409 + cap 403 + approve from awaiting_clarification ✓
- [X] T085 [P] [US4] covered in ClarificationFlowTest: vendor replies → status pending + message appended ✓
- [X] T086 [P] [US4] covered in ClarificationFlowTest: clarification beyond MAX_CLARIFICATIONS returns 403 ✓
- [X] T087 [P] [US4] covered in ClarificationFlowTest: admin can approve a CR in awaiting_clarification ✓

**US4 checkpoint**: Clarification loop works in both directions; cap enforced.

---

## Phase 7: User Story 5 — Non-Material Edits Bypass Approval Queue (P2) {#us5}

**Story goal**: Vendor edits internal-only fields (e.g., `internal_notes`, `vendor_contact_phone`); changes apply directly; no `service_change_request` is created.

**Independent test**: Per `spec.md` US5 — edit non-material field on `published` service, assert live row updated immediately, no CR row created.

### Implementation (US5)

- [ ] T088 [US5] In each of `VendorRentalServiceController`, `VendorSaleServiceController`, `VendorDigitalServiceController` (already modified in US1), confirm the non-material branch falls through to the existing direct-apply path AND verify that the existing `MarkServicePendingReviewForMaterialEditAction` no longer fires for `published` services (its `published` short-circuit from T035 handles this). Add explicit comment block referencing FR-EXT-009.
- [ ] T089 [US5] In `SubmitServiceChangeRequestAction` add an explicit invariant: if a save contains both material and non-material fields, the entire save MUST be staged (FR-EXT-009 — no partial application). Confirmed by test below.

### Tests (US5)

- [ ] T090 [P] [US5] `tests/Feature/Modules/Catalog/ServiceChangeRequest/NonMaterialEditBypassTest.php` — three `it()` blocks: edit only `internal_notes` (and per type: rental `internal_notes`, sale `internal_sku`, digital `internal_notes`), assert live row updated immediately, no `service_change_requests` row created, response is 200 not 202
- [ ] T091 [P] [US5] `tests/Feature/Modules/Catalog/ServiceChangeRequest/MixedMaterialAndNonMaterialEditTest.php` — touch one material + one non-material field in the same save; assert entire save is staged (CR row created with both proposed; live row unchanged for BOTH fields)
- [ ] T092 [P] [US5] `tests/Feature/Modules/Catalog/ServiceChangeRequest/DraftServiceEditBypassTest.php` — material edit on a `draft` or `pending_review` service bypasses the staged path entirely; existing Phase 8.0 moderation rules continue to apply

**US5 checkpoint**: Material-only gating verified; non-material edits never queue admin.

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Edge cases, lifecycle cleanup, regression protection, and backfill PRs.

- [X] T093 [P] Implement `app/Modules/Catalog/Application/Actions/CancelServiceChangeRequestAction.php` invoked by vendor suspension and service archive flows; sets status to `cancelled_vendor_suspended` or `cancelled_service_unavailable` ✓
- [ ] T094 [P] Wire `CancelServiceChangeRequestAction` into the existing `SuspendVendorAction` (Identity module) and `ArchiveServiceAction` (Catalog) via `DB::afterCommit` event subscription — no direct model imports across modules
- [X] T095 [P] `tests/Feature/Modules/Catalog/ServiceEditApproval/CancellationAndAtomicityTest.php` — vendor suspension cancels all open CRs + leaves decided CRs untouched ✓
- [X] T096 [P] covered in CancellationAndAtomicityTest: archiving a service cancels its open CR (pending + awaiting_clarification) ✓
- [X] T097 [P] covered in CancellationAndAtomicityTest: approval + field write atomicity verified (if apply fails CR status not committed) ✓
- [X] T098 [P] covered in CancellationAndAtomicityTest: ServiceChangeRequestMessage update throws LogicException (append-only enforcement) ✓
- [ ] T099 Add documentation block to `docs/specs/02_Tech_Decisions.md` cross-reference about staged edits (no decision change; just a pointer to `ADR-0035`)
- [ ] T100 [P] Run `./vendor/bin/pint` and `./vendor/bin/phpstan analyse` to enforce style + types
- [ ] T101 [P] Run `php artisan filament:cache-components` and verify the page renders for an admin user in EN AND AR (per Filament inviolable rule #10 in `filament-components.md`)
- [ ] T102 Open backfill PR for `docs/specs/11_DB_Schema.md` adding the three new tables to the Catalog section (count 13 → 16; total 60 → 63)
- [ ] T103 [P] Open backfill PR for `docs/specs/01_PRD.md` §5 adding FR-EXT-001..015
- [ ] T104 [P] Open backfill PR for `docs/specs/09_Phasing_Plan.md` inserting "Phase 8.0.1 — Staged Material Edit Approval (2 days)" after Phase 8.0
- [ ] T105 [P] Open backfill PR for `.claude/rules/schema-cheatsheet.md` Catalog inventory line and append `service_change_requests`, `service_change_request_items`, `service_change_request_messages`
- [ ] T106 [P] Update `.specify/memory/project-index.md` with `ADR-0035` row and the three new models / page / events

---

## Dependencies

```text
Phase 1 (Setup)  ──►  Phase 2 (Foundational)  ──►  Phase 3 (US1, P1)
                                              ──►  Phase 4 (US2, P1) [requires Phase 3]
                                              ──►  Phase 5 (US3, P1) [parallel with Phase 4]
                                              ──►  Phase 6 (US4, P2) [requires Phase 4 + 5]
                                              ──►  Phase 7 (US5, P2) [requires Phase 3]
                                              ──►  Phase 8 (Polish) [requires all stories]
```

- **MVP scope** = Phases 1 + 2 + 3 + 4 + 5 (US1 + US2 + US3, the three P1 stories). Without US2 you can't approve; without US3 you can't reject; without US1 there's nothing to approve. All three must ship together.
- **US4 and US5 (P2)** can ship in a follow-up release or together with the P1 trio depending on capacity.

## Parallel Execution Examples

- **In Phase 2**: T007, T008, T012–T014, T015–T017, T018–T022, T023–T025 are all `[P]` — they touch distinct files and have no inter-dependencies. Run them concurrently after T011 lands the parent model.
- **In Phase 3 (US1)**: T031 and T032 modify per-type controllers — `[P]` with each other; T033–T036 touch distinct resource/listener files — `[P]`; tests T039–T043 each in their own file — `[P]`.
- **In Phase 4 (US2)**: T050 (blade view), T052 (listener extension), T053 (notification listener), T054 (seeder), T055 (badge widget) are all `[P]`.
- **Tests across stories**: After all production code in Phases 3–7 lands, every test file is independent (`[P]`) — run the full Pest suite with `--parallel`.

## Implementation Strategy

1. **Land ADR-0035 first** (T001). No code merges until accepted.
2. **Phase 1 + 2** in one PR: migrations, models, enums, events, factories. No HTTP surface; pure foundations.
3. **Phase 3 (US1)** in its own PR with its tests green. Vendor flow lands; live service still untouchable.
4. **Phase 4 + Phase 5 (US2 + US3)** in one combined PR — approve + reject ship together since rejecting a pending CR is meaningless without approval, and vice versa.
5. **Phase 6 (US4)** as a follow-up PR.
6. **Phase 7 (US5)** is largely an assertion of existing behavior; can fold into the US1 PR if T088 is just a comment, or ship as its own small PR.
7. **Phase 8** rolls into a polish PR with the backfill commits.

## Independent Test Criteria Recap

| Story | Independent Test (one-liner) |
|---|---|
| US1 | Live row hash unchanged + 1 pending CR row + 202 response |
| US2 | After approve, live row hash equals proposed hash + N audit rows + Scout queue dispatched |
| US3 | After reject, live row hash unchanged + bilingual notes persisted + vendor can submit fresh CR |
| US4 | Clarification round transitions correctly + 4th round blocked + vendor reply returns to pending |
| US5 | Non-material edit → 200 with updated live row + zero CR rows |

## Total

- **Tasks**: 106 (T001–T106)
- **Setup**: 6 tasks (T001–T006)
- **Foundational**: 21 tasks (T007–T027)
- **US1 (P1)**: 16 tasks (T028–T043)
- **US2 (P1)**: 18 tasks (T044–T061)
- **US3 (P1)**: 12 tasks (T062–T073)
- **US4 (P2)**: 14 tasks (T074–T087)
- **US5 (P2)**: 5 tasks (T088–T092)
- **Polish**: 14 tasks (T093–T106)
