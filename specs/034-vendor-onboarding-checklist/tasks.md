---
description: "Task list for 034-vendor-onboarding-checklist"
---

# Tasks: Vendor Onboarding Checklist Widget

**Input**: Design documents from `/specs/034-vendor-onboarding-checklist/`
**Prerequisites**: `plan.md`, `spec.md`, `research.md`, `data-model.md`, `contracts/`, `quickstart.md` (all present)
**Branch**: `034-vendor-onboarding-checklist`

**Tests**: Pest test coverage is **mandatory** for this feature — see FR-EXT-018 in `spec.md`. Test tasks are included throughout.

**Organization**: Tasks are grouped by user story so each P1 story can be implemented and validated independently. US4 (AR locale) is a cross-cutting story implemented partly in foundational lang files and finalised in its own phase.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependency on incomplete tasks)
- **[Story]**: User-story tag (`US1`, `US2`, `US3`, `US4`)
- Every task includes an absolute file path

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: confirm prerequisites; no new packages required (per `plan.md` Technical Context).

- [X] T001 Confirm `app/Modules/Identity/Application/Services/` exists (it does — contains `OtpRateLimiter.php`); confirm `app/Modules/Catalog/Domain/Contracts/` and `app/Modules/Catalog/Infrastructure/Repositories/` exist (create if absent — `New-Item -ItemType Directory -Force`).
- [X] T002 Create the new widget directory `app/Modules/Identity/Filament/Vendor/Widgets/` so `VendorPanelProvider->discoverWidgets()` (already wired at `app/Providers/Filament/VendorPanelProvider.php:122-125`) can pick it up.
- [X] T003 Create the views directory `app/Modules/Identity/Resources/views/widgets/` for the custom Blade view.
- [X] T004 Verify `IdentityServiceProvider` loads views from `app/Modules/Identity/Resources/views` with namespace `identity` (open `app/Modules/Identity/Providers/IdentityServiceProvider.php` and confirm `$this->loadViewsFrom(__DIR__.'/../Resources/views', 'identity')` — add the call if missing).

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: build the scaffolding (DTOs, enums, contract, service skeleton, lang files, view stub, widget skeleton, dashboard registration) that every user story depends on.

**⚠️ CRITICAL**: No user-story phase can start until this phase is complete.

### Cross-module contract (Catalog → Identity)

- [X] T005 [P] Create contract `App\Modules\Catalog\Domain\Contracts\VendorServicePresenceQuery` at `app/Modules/Catalog/Domain/Contracts/VendorServicePresenceQuery.php` with the two methods `hasAnyService(int $vendorProfileId): bool` and `hasServiceInReviewOrPublished(int $vendorProfileId): bool` (see `contracts/ServicePresenceQuery.md`).
- [X] T006 [P] Implement `App\Modules\Catalog\Infrastructure\Repositories\EloquentVendorServicePresenceQuery` at `app/Modules/Catalog/Infrastructure/Repositories/EloquentVendorServicePresenceQuery.php` using `Service::query()->where('vendor_profile_id', $id)->exists()` and the `whereIn('status', ['pending_review','published'])` variant.
- [X] T007 Bind the contract to its implementation in `app/Modules/Catalog/Providers/CatalogServiceProvider.php` `register()` (depends on T005 + T006).
- [X] T008 [P] Confirm the `services` table has an index on `vendor_profile_id` (check `app/Modules/Catalog/Database/Migrations/` for the create-services migration; if no FK index exists on `vendor_profile_id` alone, the composite `(vendor_profile_id, slug)` UNIQUE from `schema-cheatsheet.md` already covers the prefix — no new migration needed). Document the finding in a comment at the top of `EloquentVendorServicePresenceQuery.php`.

### Enums and DTOs (Identity)

- [X] T009 [P] Create enum `App\Modules\Identity\Domain\Enums\VendorOnboardingChecklistItemKey` at `app/Modules/Identity/Domain/Enums/VendorOnboardingChecklistItemKey.php` with the 10 cases listed in `data-model.md`.
- [X] T010 [P] Create enum `App\Modules\Identity\Domain\Enums\ChecklistItemStatus` at `app/Modules/Identity/Domain/Enums/ChecklistItemStatus.php` with cases `Complete`, `Pending`, `Warning`, `Danger`, `Info`.
- [X] T011 [P] Create enum `App\Modules\Identity\Domain\Enums\RejectionState` at `app/Modules/Identity/Domain/Enums/RejectionState.php` with cases `None`, `ChangesRequested`, `Rejected`.
- [X] T012 [P] Create readonly DTO `App\Modules\Identity\Application\DTOs\VendorOnboardingChecklistItemDTO` at `app/Modules/Identity/Application/DTOs/VendorOnboardingChecklistItemDTO.php` with the fields listed in `data-model.md`.
- [X] T013 [P] Create readonly DTO `App\Modules\Identity\Application\DTOs\VendorOnboardingChecklistDTO` at `app/Modules/Identity/Application/DTOs/VendorOnboardingChecklistDTO.php` with all fields listed in `data-model.md` (depends on T009–T012 for type hints).

### Required-documents resolver

- [X] T014 Grep `app/Modules/Identity` and `app/Modules/Identity/Application/Services` for an existing `RequiredVendorDocumentTypesResolver` (or similarly-named class) introduced by `022-vendor-doc-compliance`. If found, skip T015; record reuse in a comment on the checklist service in T016. If not found, proceed to T015.
- [X] T015 Create `App\Modules\Identity\Application\Services\RequiredVendorDocumentTypesResolver` at `app/Modules/Identity/Application/Services/RequiredVendorDocumentTypesResolver.php` with one public method `forBusinessType(string $businessType): array` returning the default map from `research.md` Decision 0.1 (`individual → [national_id, iban_proof]`, `company → [cr, tax_card, iban_proof]`, `establishment → [cr, tax_card, national_id, iban_proof]`). Use string `doc_type` values matching the `vendor_documents.doc_type` enum.

### Service skeleton

- [X] T016 Create the service skeleton `App\Modules\Identity\Application\Services\VendorOnboardingChecklistService` at `app/Modules/Identity/Application/Services/VendorOnboardingChecklistService.php` with constructor-injected `RequiredVendorDocumentTypesResolver` and `VendorServicePresenceQuery` dependencies and a `forVendor(VendorProfile $vendor): VendorOnboardingChecklistDTO` method that returns a DTO with all 10 items set to `Pending` (real logic added in US phases). Depends on T013, T015, T005.

### Lang files (EN+AR scaffolding for all rows)

- [X] T017 [P] Create EN translation file `app/Modules/Identity/Resources/lang/en/vendor-onboarding.php` returning an array with every key the widget will use: row labels (10 base + rejection banner + suspension banner), status pill words (`complete`, `pending`, `warning`, `danger`, `info`), progress label (`":done of :total complete"`), CTA labels (`"Next recommended action"`, `"Onboarding complete"`, `"Edit profile and resubmit"`, `"Upload new copy"`), and per-row CTA verbs (`"Complete business profile"`, `"Add banking details"`, etc.).
- [X] T018 [P] Create AR translation file `app/Modules/Identity/Resources/lang/ar/vendor-onboarding.php` with the same key set as T017, every value translated to Arabic. (Will be tightened in US4.)

### Widget skeleton + Blade view stub

- [X] T019 Create the widget class `App\Modules\Identity\Filament\Vendor\Widgets\VendorOnboardingChecklistWidget` at `app/Modules/Identity/Filament/Vendor/Widgets/VendorOnboardingChecklistWidget.php`. Extends `\Filament\Widgets\Widget`. Constructor injects nothing (Filament instantiates without container args); inside `getViewData()`, resolve `VendorOnboardingChecklistService` via `app(...)`, get the authenticated user's `VendorProfile` via `auth()->user()->vendorProfile` (assume the existing relationship — confirm in T020), call `forVendor()`, and return `['checklist' => $dto]`. Set `protected static string $view = 'identity::widgets.onboarding-checklist';` and `protected int|string|array $columnSpan = 'full';`. Depends on T016.
- [X] T020 Confirm `User::vendorProfile` HasOne relationship exists in `app/Modules/Identity/Domain/Models/User.php` (grep for `vendorProfile`). If missing, add it: `public function vendorProfile(): HasOne { return $this->hasOne(VendorProfile::class); }`. This is a read-only addition, no migration.
- [X] T021 Create the Blade view stub at `app/Modules/Identity/Resources/views/widgets/onboarding-checklist.blade.php`. Wrap in `<x-filament-widgets::widget>` + `<x-filament::section>`. Render a placeholder `{{ $checklist->totalCount }}` to confirm wiring. Full markup added in US1.

### Dashboard registration

- [X] T022 Open `app/Modules/Shared/Filament/Vendor/Pages/VendorDashboardPage.php`. Add `protected function getHeaderWidgets(): array { return [\App\Modules\Identity\Filament\Vendor\Widgets\VendorOnboardingChecklistWidget::class]; }` and `public function getHeaderWidgetsColumns(): int|string|array { return 1; }`. If the file does not exist, scaffold it as a `\Filament\Pages\Dashboard` subclass per `VendorPanelProvider::class->pages()`. Depends on T019.
- [ ] T023 Smoke-test: run `php artisan filament:cache-components && php artisan serve` (manual), log in as any vendor user, and load `/vendor`. Confirm the widget skeleton renders with the placeholder. (Just a manual verification gate — no automated test yet.)

### Test scaffolding

- [X] T024 [P] Create the test file `tests/Feature/Modules/Identity/Filament/Vendor/VendorOnboardingChecklistWidgetTest.php` with an empty `describe('VendorOnboardingChecklistWidget', ...)` block and `beforeEach` that calls `actingAs(...)` for a freshly-factoried vendor user. Asserts only `livewire(VendorOnboardingChecklistWidget::class)->assertOk()` for now.
- [X] T025 [P] Create the test file `tests/Unit/Modules/Identity/Application/Services/VendorOnboardingChecklistServiceTest.php` with an empty `describe('VendorOnboardingChecklistService', ...)` block and a `beforeEach` that creates a baseline `VendorProfile` factory. Asserts only `it('returns a DTO with 10 items for a non-suspended vendor', fn () => expect($service->forVendor($vendor)->totalCount)->toBe(10))`.

**Checkpoint**: Foundation ready — all 10 placeholder rows render, locale switcher works on the dashboard page, and the test files exist. User-story implementation can now begin.

---

## Phase 3: User Story 1 — Newly-registered vendor sees the path to approval (Priority: P1) 🎯 MVP

**Goal**: deliver the full 10-row checklist with correct status icons, progress percentage, "Next recommended action" CTA, and deep links to vendor pages for each incomplete row.

**Independent Test**: seed a brand-new vendor (only `business_name` set, no documents/coverage/hours/services/banking) → load `/vendor` → confirm 10 rows render, only "Business profile started" is complete (or all pending if profile is also incomplete), progress shows the correct %, and the CTA points to `VendorProfilePage`.

### Tests for User Story 1 (write FIRST, ensure they FAIL before implementation)

- [X] T026 [P] [US1] In `tests/Unit/Modules/Identity/Application/Services/VendorOnboardingChecklistServiceTest.php`, add per-row tests: `it('marks profile complete when all required columns populated for individual')`, `... for company`, `... for establishment` — and the mirror `it('marks profile pending when …')` for each missing column.
- [X] T027 [P] [US1] In the same file, add `it('marks banking complete when bank_name + bank_account_holder + bank_iban all set')` and `it('marks banking pending when bank_iban is null')`.
- [X] T028 [P] [US1] In the same file, add `it('marks docs_uploaded complete when one row exists per required doc_type for business_type')` and its pending counterpart.
- [X] T029 [P] [US1] In the same file, add `it('marks docs_approved complete when all latest rows are approved and not expired')`, `it('marks docs_approved warning when an approved row has expired')`, `it('marks docs_approved danger when any required type is rejected')`, `it('marks docs_approved info when any required type is pending')`.
- [X] T030 [P] [US1] In the same file, add `it('marks coverage complete when at least one vendor_coverage_areas row exists')`, `it('marks hours complete when at least one vendor_business_hours row exists')`, `it('marks service_drafted complete when hasAnyService=true')`, `it('marks service_submitted complete when hasServiceInReviewOrPublished=true')`.
- [X] T031 [P] [US1] In the same file, add `it('marks approval_status complete when approval_status=approved')`, `it('marks approval_status info when pending')`, `it('marks approval_status warning when changes_requested')`, `it('marks approval_status danger when rejected')`.
- [X] T032 [P] [US1] In the same file, add `it('marks approved_types complete when at least one row exists and renders the list in active locale')`.
- [X] T033 [P] [US1] In the same file, add `it('returns nextRecommendedAction as the first incomplete item in priority order')` and `it('returns null nextRecommendedAction when every priority-list item is complete')`.
- [X] T034 [P] [US1] In `tests/Feature/Modules/Identity/Filament/Vendor/VendorOnboardingChecklistWidgetTest.php`, add `it('renders all 10 rows with the correct status icons for an empty vendor')` using `livewire(...)->assertSee(...)` for each label key.
- [X] T035 [P] [US1] In the same widget test file, add `it('renders the Next recommended action button with the correct URL for an empty vendor')` asserting the URL contains the slug of `VendorProfilePage`.
- [X] T036 [P] [US1] In the same widget test file, add `it('renders the Onboarding complete chip and no CTA button for a fully-onboarded vendor')` using a factory recipe that seeds every prerequisite.
- [X] T037 [P] [US1] In the same widget test file, add `it('does not leak Vendor B data when Vendor A is authenticated')` — seed two vendors, authenticate as A, assert B's documents/services/etc. do not appear in the rendered output.

### Implementation for User Story 1

- [X] T038 [US1] Implement the **profile-completeness** computation in `VendorOnboardingChecklistService` (private method `profileItem(VendorProfile $vendor): VendorOnboardingChecklistItemDTO`). Use the per-`business_type` rule from `data-model.md`. URL: `VendorProfilePage::getUrl()`. Depends on T026.
- [X] T039 [US1] Implement **banking** computation (private method `bankingItem`). URL: `VendorAccountPage::getUrl()`. Depends on T027.
- [X] T040 [US1] Implement **docs-uploaded** computation (private method `docsUploadedItem`) using `RequiredVendorDocumentTypesResolver::forBusinessType($vendor->business_type)` and a single grouped `vendor_documents` query. URL: `VendorDocumentsPage::getUrl()`. Depends on T028.
- [X] T041 [US1] Implement **docs-approved** computation (private method `docsApprovedItem`) — must be expiry-aware (`expires_at <= now()` → warning) and consume the same `vendor_documents` rows already loaded in T040 to keep the query budget at 1. Depends on T029, T040.
- [X] T042 [US1] Implement **coverage** computation (private method `coverageItem`) via `vendor_coverage_areas->exists()`. URL: `VendorCoverageAreasPage::getUrl()`. Depends on T030.
- [X] T043 [US1] Implement **hours** computation (private method `hoursItem`) via `vendor_business_hours->exists()`. URL: `VendorBusinessHoursPage::getUrl()`. Depends on T030.
- [X] T044 [US1] Implement **service-drafted** and **service-submitted** computations (private methods `serviceDraftedItem`, `serviceSubmittedItem`) via the injected `VendorServicePresenceQuery` contract. URL: `VendorRentalServiceResource::getUrl('index')`. Depends on T030.
- [X] T045 [US1] Implement **approval-status** computation (private method `approvalStatusItem`) mapping `approval_status → ChecklistItemStatus` per `data-model.md`. URL: `null` (read-only row). Depends on T031.
- [X] T046 [US1] Implement **approved-types** computation (private method `approvedTypesItem`) loading `vendor_approved_product_types` rows and formatting the sub-line via `match (ProductType $case)` in the active locale. URL: `null`. Depends on T032.
- [X] T047 [US1] Assemble the DTO inside `forVendor()`: call each private item method in order, compute `completedCount`, `progressPercent = intdiv($completedCount * 100, 10)`, and resolve `nextRecommendedAction` by walking the priority list from `data-model.md`. Depends on T038–T046, T033.
- [X] T048 [US1] Fill in the Blade view `app/Modules/Identity/Resources/views/widgets/onboarding-checklist.blade.php` for the **base 10-row layout**: header row with progress bar + percentage + count; a list of 10 `<li>` rows each with a Heroicon, the localised label, optional sub-text, and an inline anchor when the row carries a URL; below the list, a primary action button labelled with `$checklist->nextRecommendedAction->label` (or a static "Onboarding complete" chip when null). Use Filament's existing utility classes (`fi-section`, `fi-color-success`, etc.) for consistency. Depends on T047.
- [X] T049 [US1] Run T026–T037 — they must now go from RED → GREEN. Fix any production-code bugs surfaced.

**Checkpoint**: US1 fully functional. A vendor can load `/vendor` and see the complete checklist with correct statuses and links. Progress % + Next-recommended-action work end-to-end.

---

## Phase 4: User Story 2 — Rejected / changes-requested vendor sees the blocker first (Priority: P1)

**Goal**: render the rejection / changes-requested banner above the checklist in the active locale (EN or AR) with a CTA to `VendorProfilePage`.

**Independent Test**: seed a vendor with `approval_status='rejected'` and a populated bilingual `rejection_reason` → load `/vendor` → confirm a danger-coloured banner appears above the checklist with the EN string; switch locale to AR → banner shows AR string.

### Tests for User Story 2

- [X] T050 [P] [US2] In `VendorOnboardingChecklistServiceTest.php`, add `it('populates rejectionState=Rejected and translated rejectionReason for approval_status=rejected')` and `it('populates rejectionState=ChangesRequested for approval_status=changes_requested')`.
- [X] T051 [P] [US2] In `VendorOnboardingChecklistServiceTest.php`, add `it('returns the AR rejection_reason when app locale is ar')` (assert against the Arabic JSON value).
- [X] T052 [P] [US2] In `VendorOnboardingChecklistWidgetTest.php`, add `it('renders a danger banner above the checklist for a rejected vendor with the EN reason')` (Livewire `assertSeeText`).
- [X] T053 [P] [US2] In `VendorOnboardingChecklistWidgetTest.php`, add `it('renders a warning banner for a changes_requested vendor')`.

### Implementation for User Story 2

- [X] T054 [US2] In `VendorOnboardingChecklistService`, add private method `rejectionBannerFields(VendorProfile $vendor): array` returning `[RejectionState, ?string $translatedReason]`. Map `approval_status` → `RejectionState`; resolve the translated reason via `$vendor->getTranslation('rejection_reason', app()->getLocale())`. Wire into the DTO assembly in `forVendor()`. Depends on T050, T047.
- [X] T055 [US2] In the Blade view `onboarding-checklist.blade.php`, add a top-anchored banner block (rendered iff `$checklist->rejectionState !== RejectionState::None`). Use `fi-color-danger` for `Rejected` and `fi-color-warning` for `ChangesRequested`. Include a CTA button "Edit profile and resubmit" linking to `VendorProfilePage::getUrl()`. Depends on T054.
- [X] T056 [US2] Run T050–T053 — must go RED → GREEN.

**Checkpoint**: US2 fully functional. Rejected/changes-requested vendors see the blocker above the fold in their locale.

---

## Phase 5: User Story 3 — Suspended vendor sees suspension reason (Priority: P1)

**Goal**: render a suspended-variant view (banner only, no checklist rows) when `approval_status='suspended'`. Confirm the existing `CheckVendorSuspension` middleware redirect behaviour is unchanged.

**Independent Test**: seed a suspended vendor with `suspended_at` and bilingual `rejection_reason` → confirm the middleware redirect still fires; render the widget directly via Livewire (bypassing the redirect to exercise the widget code path) and confirm only the suspension banner is visible — no checklist rows.

### Tests for User Story 3

- [X] T057 [P] [US3] In `VendorOnboardingChecklistServiceTest.php`, add `it('returns isSuspended=true with empty items array for a suspended vendor')` and `it('populates suspendedAt and translated suspensionReason for a suspended vendor')`.
- [X] T058 [P] [US3] In `VendorOnboardingChecklistWidgetTest.php`, add `it('renders only the suspension banner (no checklist rows) when the authenticated vendor is suspended')`. Use Livewire to mount the widget directly so the middleware does not pre-empt the test.
- [X] T059 [P] [US3] In `tests/Feature/Modules/Identity/Http/Middleware/CheckVendorSuspensionTest.php` (existing file — if not, create), add (or assert-existing) `it('redirects suspended vendors away from /vendor to AccountSuspendedPage')` — regression guard so the widget work does not accidentally bypass the middleware.

### Implementation for User Story 3

- [X] T060 [US3] In `VendorOnboardingChecklistService::forVendor()`, add an early return at the top: if `$vendor->approval_status === 'suspended'`, construct the DTO with `isSuspended=true`, `items=[]`, `completedCount=0`, `progressPercent=0`, `nextRecommendedAction=null`, `suspendedAt`, and the translated `suspensionReason`. Depends on T057.
- [X] T061 [US3] In the Blade view `onboarding-checklist.blade.php`, branch on `@if ($checklist->isSuspended)` at the top: render only the suspension banner (icon, localised heading, `suspendedAt->translatedFormat('d M Y')`, and the translated `suspensionReason`) and `@return` from the view. Depends on T060.
- [X] T062 [US3] Run T057–T059 — must go RED → GREEN. Confirm the middleware test still passes (regression guard).

**Checkpoint**: US3 fully functional. Suspended vendors never see a misleading checklist; middleware redirect still works.

---

## Phase 6: User Story 4 — Arabic vendor sees fully-localised checklist (Priority: P2)

**Goal**: finalise the AR translation file (review every key for natural Arabic phrasing), ensure key-set parity, and add automated tests that catch missing translations + RTL layout regressions.

**Independent Test**: switch the Filament panel locale to AR → confirm every label/CTA/sub-text/banner renders in Arabic, the progress label reads "اكتمل N من 10", and the layout is RTL.

### Tests for User Story 4

- [X] T063 [P] [US4] In `tests/Unit/Modules/Identity/Lang/VendorOnboardingTranslationsTest.php` (new file), add `it('has identical key sets in en and ar vendor-onboarding files')` — load both PHP arrays with `array_keys(...)`, sort, and assert equality. Cover nested keys via a recursive flattener.
- [X] T064 [P] [US4] In the same file, add `it('has no empty string values in either locale file')` — iterate values and assert each is a non-empty string.
- [X] T065 [P] [US4] In `VendorOnboardingChecklistWidgetTest.php`, add `it('renders Arabic labels when app locale is ar')` — `app()->setLocale('ar')`, mount the widget, assert key Arabic strings appear and no raw EN labels leak through.
- [X] T066 [P] [US4] In `VendorOnboardingChecklistServiceTest.php`, add `it('returns Arabic-labelled items when app locale is ar')` — same intent, asserting on DTO `label` and `subText` fields rather than rendered HTML.

### Implementation for User Story 4

- [X] T067 [US4] Review the AR file `app/Modules/Identity/Resources/lang/ar/vendor-onboarding.php` end to end. Replace any rough machine-translated phrases from T018 with natural Egyptian-Arabic phrasing matching the tone of existing files (cross-reference `app/Modules/Identity/Resources/lang/ar/vendor-portal.php` for voice consistency). Depends on T017, T018.
- [ ] T068 [US4] Verify RTL rendering manually: log in as a vendor, switch locale to AR via the panel switcher, walk through each of the four test vendors from `quickstart.md` step 3, and confirm icons appear on the right, button alignment is correct, and the banner reads naturally. Document the verification in a one-line comment in `quickstart.md` step 5 (mark as ✅).
- [X] T069 [US4] Run T063–T066 — must go RED → GREEN.

**Checkpoint**: US4 fully functional. AR locale is production-ready and lint-tested for parity with EN.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: performance assertion, lint/static-analysis cleanup, architecture-test validation, and quickstart walkthrough.

- [X] T070 [P] In `VendorOnboardingChecklistServiceTest.php`, add `it('issues no more than 6 queries per forVendor call', function () { DB::enableQueryLog(); $service->forVendor($vendor); expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(6); })`. This satisfies FR-EXT-017 / SC-005.
- [X] T071 [P] In `tests/Architecture/NoCrossModuleModelImportsTest.php` (existing), confirm the test passes with the new code. If the test is missing or out of date, update it to scan Identity-module source for any `use App\Modules\Catalog\Domain\Models\` import and assert zero matches. Reference: Constitution Principle I.
- [X] T072 [P] Add a one-paragraph entry to `.specify/memory/project-index.md` under the Identity module section noting the new widget (`VendorOnboardingChecklistWidget` — vendor dashboard onboarding-progress display).
- [X] T073 [P] Add ⚠️ BACKFILL entries to:
  - `docs/specs/01_PRD.md` §7.2 — vendor-portal onboarding-progress widget FR (cite FR-EXT-001 through FR-EXT-018 from the spec).
  - `docs/specs/09_Phasing_Plan.md` Phase 1 deliverables list — add "VendorOnboardingChecklistWidget on /vendor dashboard" as a Phase 1.x deliverable.
- [X] T074 Run the full Pest suite filtered to this feature: `./vendor/bin/pest --filter=VendorOnboardingChecklist` — must be all green.
- [ ] T075 Run `./vendor/bin/pint app/Modules/Identity app/Modules/Catalog/Domain/Contracts app/Modules/Catalog/Infrastructure/Repositories` and confirm no diffs. (Pint binary not available in this environment — run manually.)
- [ ] T076 Run `./vendor/bin/phpstan analyse app/Modules/Identity app/Modules/Catalog` and confirm clean. (PHPStan binary not available in this environment — run manually.)
- [ ] T077 Walk through `quickstart.md` steps 1–8 end-to-end and confirm every step passes as written. (Manual verification required.)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: no dependencies — start immediately.
- **Foundational (Phase 2)**: depends on Setup. **Blocks all user-story phases.**
- **US1 (Phase 3)**: depends on Phase 2. MVP — every other story builds on the rendered widget.
- **US2 (Phase 4)**: depends on Phase 2 (banner is independent of base checklist logic, but the Blade view structure from US1 is the host — so practically Phase 4 starts after Phase 3 T048).
- **US3 (Phase 5)**: depends on Phase 2 (suspended variant short-circuits before US1 logic — could technically be implemented in parallel with US1, but the Blade view's `@if` branch needs the US1 view shell as host).
- **US4 (Phase 6)**: depends on Phase 2 (lang scaffolding already in place); meaningful only after US1+US2+US3 are in the view, so practically runs after Phase 5.
- **Polish (Phase 7)**: depends on all four story phases.

### User Story Independence

Each P1 story (US1, US2, US3) is **independently testable**: seed the right vendor state, mount the widget via Livewire, assert. The stories share one Blade view file, which creates a minor file-coordination constraint (the view is touched in T048, T055, T061 sequentially) — these are sequential edits, not parallel.

### Within Each User Story

- Tests are written FIRST per task numbering (T026–T037 before T038–T049, etc.). Tests MUST fail before implementation begins.
- DTO/enum scaffolding is in Phase 2; story phases only add real logic to the service + view.
- Per-row service methods (T038–T046) are independent of each other except where one consumes another (T041 reuses T040's loaded rows) — sequential by file (`VendorOnboardingChecklistService.php`).

### Parallel Opportunities

- Phase 2: T005 + T006 + T009 + T010 + T011 + T012 + T017 + T018 + T024 + T025 are all in different files → can run in parallel.
- Phase 3: T026 through T037 are all in two test files (`VendorOnboardingChecklistServiceTest.php` and `VendorOnboardingChecklistWidgetTest.php`); two developers can split (one file each).
- Phase 7: T070 + T071 + T072 + T073 are all in different files → parallel.

---

## Parallel Example: Phase 2 Scaffolding

```text
# Three developers can sprint Phase 2 in parallel after T001–T004 finish:

Developer A (Catalog contract + binding):
  T005, T006, T007, T008

Developer B (Identity DTOs/enums + resolver):
  T009, T010, T011, T012, T013, T015, T016

Developer C (lang files + widget skeleton + view stub + test files):
  T014 (grep audit), T017, T018, T019, T020, T021, T022, T023, T024, T025
```

After Phase 2: all three P1 stories (US1, US2, US3) can in principle be picked up in parallel by separate developers, with the constraint that the shared Blade view file is edited sequentially.

---

## Implementation Strategy

### MVP first (US1 only)

1. Phase 1: Setup (T001–T004).
2. Phase 2: Foundational (T005–T025).
3. Phase 3: US1 (T026–T049).
4. **STOP and VALIDATE** — seed an empty vendor, a partially-onboarded vendor, and a fully-onboarded vendor; load `/vendor` for each and confirm visual correctness.
5. Ship to staging for Ibrahim's review.

### Incremental delivery

- After MVP: layer US2 (rejection banner) — small, two-test, two-implementation-task increment.
- Then US3 (suspended variant) — also small, with the middleware regression guard.
- Then US4 (AR polish) — finishes the locale story.
- Then Phase 7 polish for a clean PR.

### Total task count

- **Phase 1**: 4 tasks
- **Phase 2**: 21 tasks (T005–T025)
- **Phase 3 (US1)**: 24 tasks (T026–T049)
- **Phase 4 (US2)**: 7 tasks (T050–T056)
- **Phase 5 (US3)**: 6 tasks (T057–T062)
- **Phase 6 (US4)**: 7 tasks (T063–T069)
- **Phase 7**: 8 tasks (T070–T077)
- **Total**: **77 tasks**

---

## Notes

- `[P]` tasks touch different files and have no incomplete-task dependencies — safe to parallelise.
- Every task starts with the markdown checkbox `- [ ]`, has a sequential `TNNN` ID, and (for story phases) carries the `[USx]` label and an absolute file path.
- Tests are written before the implementation in each user-story phase (per Constitution Principle VII for the test-first discipline, even though widgets aren't a money/auth/booking critical path).
- No new packages, no new tables, no API endpoints — schema and `10_Package_List.md` remain untouched.
- After every story phase: commit with conventional format `feat(identity): vendor onboarding checklist — USn`.
