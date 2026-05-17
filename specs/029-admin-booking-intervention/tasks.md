---
description: "Tasks for 029-admin-booking-intervention — Admin Booking Intervention Page"
---

# Tasks: Admin Booking Intervention Page

**Input**: Design documents from `specs/029-admin-booking-intervention/`
**Prerequisites**: `plan.md` ✅, `spec.md` ✅, `research.md` ✅, `data-model.md` ✅, `contracts/{actions,events,permissions}.md` ✅, `quickstart.md` ✅

**Tests**: REQUIRED. The spec mandates Pest coverage (FR-EXT-020) and constitution principle VII makes test-first non-negotiable for booking state transitions. Tests precede or land alongside implementation, never deferred.

**Organization**: Tasks are grouped by the seven user stories in `spec.md` (P1: US1–US4; P2: US5–US7). Each story is independently testable and deliverable.

**Phase alignment**: Extends Phase 6.5 (Admin Booking Override) + Phase 7.0 per `09_Phasing_Plan.md`. ⚠️ Phase backfill flag inherited from `spec.md`.

## Format

`- [ ] [TaskID] [P?] [Story?] Description with absolute file path`

- **[P]** = parallelizable (different file, no in-flight dependency)
- **[Story]** = `[US1]`…`[US7]` for user-story phase tasks; absent for Setup / Foundational / Polish

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Branch, ADR, config, and preflight checks before any code lands.

- [ ] T001 Create git branch `029-admin-booking-intervention` from the parent branch once `028-financial-ledger-hardening` settles (do not branch from a dirty working tree).
- [X] T002 Draft and accept `docs/adr/ADR-0029-admin-booking-intervention-page.md` covering Status, Context, Decision (six new Actions + zero replacement-assignment surface), Consequences, Alternatives Considered, Internal Decisions (throttle TTLs 5 min / 4 h, suggestion cap 5, stalled threshold 48 h), Related ADRs (0001, 0003, 0028).
- [X] T003 Add the `intervention` key to `config/booking.php` with keys: `stalled_threshold_hours` (default 48), `deadline_grace_period_minutes` (default 0), `vendor_reminder_cooldown_minutes` (default 5), `customer_review_reminder_cooldown_hours` (default 4), `suggest_max_candidates` (default 5).
- [X] T004 [P] Preflight check — verify `chat_threads` migration state. Run `Get-ChildItem app/Modules/Communication/Database/Migrations -Filter "*chat_threads*"`. If empty, flag US6 tasks as **deferred** (cut-list item 1 from `plan.md`) and skip T040–T046.
- [X] T005 [P] Preflight check — inspect `booking_admin_interventions.intervention_type` column type. If `ENUM(...)`, add task T013 (enum-extension migration). If `VARCHAR`, skip T013.
- [X] T006 [P] Add entry to `.specify/memory/project-index.md` under "Current Phase" referencing this feature folder and ADR-0029.

**Checkpoint**: ADR accepted; config defaults in place; gate decisions for US6 + enum migration locked.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Schema, contracts, enums, permissions, base resource shell, architecture tests. No user-story code starts until this completes.

⚠️ **CRITICAL**: All user stories depend on this phase.

### Migrations (constitution layer order step 2)

- [X] T007 Create migration `app/Modules/Booking/Database/Migrations/2026_05_16_100001_seed_intervention_notification_templates.php` that INSERTs the seven `notification_templates` rows (event keys: `booking.vendor.reminder`, `booking.vendor.timed_out_by_admin`, `booking.vendor.timed_out_by_admin.customer`, `booking.alternatives.suggested`, `booking.chat.frozen`, `booking.chat.resumed`, `booking.customer_review.reminder`), each with EN + AR `subject` and `body` JSON, audience (`vendor`/`customer`), channel (`in_app` + `push`).
- [X] T008 Conditional migration (skip per T005 if column is VARCHAR) — `app/Modules/Booking/Database/Migrations/2026_05_16_100002_extend_intervention_type_enum.php` to add `vendor_reminder`, `chat_frozen`, `chat_resumed`, `customer_review_reminder` to the ENUM column on `booking_admin_interventions`.

### Domain extensions (layer order step 3)

- [X] T009 [P] Extend enum `app/Modules/Booking/Domain/Enums/InterventionType.php` with four new cases: `VendorReminder = 'vendor_reminder'`, `ChatFrozen = 'chat_frozen'`, `ChatResumed = 'chat_resumed'`, `CustomerReviewReminder = 'customer_review_reminder'`.
- [X] T010 [P] Extend `app/Modules/Booking/Application/DTOs/AdminInterventionDTO.php` with three new readonly fields: `?int $bookingVendorId = null`, `?int $proposedVendorId = null`, `?array $suggestedVendorIds = null`. Existing callers (`ForceCancelBookingAction`) MUST stay green.
- [X] T011 [P] Create DTO `app/Modules/Booking/Application/DTOs/SuggestedAlternativeVendorsDTO.php` with `int $bookingId`, `int $adminId`, `array<int> $vendorProfileIds`, `string $reason`.

### Cross-module contracts (constitution principle I)

- [X] T012 [P] Create contract `app/Modules/Communication/Domain/Contracts/AdminInboxWriter.php` with method `create(string $sourceType, int $sourceId, AdminInboxSeverity $severity, array $title, array $body, ?int $assignedToAdminId = null): AdminInboxItem`.
- [X] T013 [P] Create implementation `app/Modules/Communication/Infrastructure/Repositories/EloquentAdminInboxWriter.php` implementing the contract. Bind in `app/Modules/Communication/Providers/CommunicationServiceProvider.php::register()`.
- [X] T014 [P] Create contract `app/Modules/Discovery/Domain/Contracts/AlternativeVendorFinder.php` with methods `findCandidates(Booking $booking, ?int $limit = null): Collection<VendorProfile>` and `validateCandidates(Booking $booking, array $vendorProfileIds): void` (throws `\InvalidArgumentException` on failure).
- [X] T015 [P] Create implementation `app/Modules/Discovery/Infrastructure/Repositories/EloquentAlternativeVendorFinder.php` per the filter logic in `research.md` §R-5. Bind in the Discovery service provider.

### Permissions and policy (layer order step 4 — auth scaffold)

- [X] T016 Edit `app/Modules/Booking/Database/Seeders/BookingPermissionsSeeder.php` to seed seven new Shield permissions: `booking.intervene.access`, `booking.intervene.send_vendor_reminder`, `booking.intervene.escalate_vendor_timeout`, `booking.intervene.suggest_alternative_vendors`, `booking.intervene.freeze_chat`, `booking.intervene.resume_customer_review`, `booking.intervene.create_note`. Grant the bundle to the `admin` role.
- [X] T017 [P] Create policy `app/Modules/Booking/Domain/Policies/BookingAdminInterventionPolicy.php` per `contracts/permissions.md`, including the FR-EXT-012 hard-boundary check (returns `false` when `intervention_type = VendorProposal` AND `proposed_vendor_id !== null`).
- [X] T018 Register the policy in `app/Modules/Booking/Providers/BookingServiceProvider.php::boot()` via `Gate::policy(BookingAdminIntervention::class, BookingAdminInterventionPolicy::class)`.

### Filament resource shell (layer order step 7 — base only; per-story tasks add columns + actions)

- [X] T019 Create directory `app/Modules/Booking/Filament/Resources/AdminBookingInterventionResource/` and `Pages/` subdir.
- [X] T020 Create `app/Modules/Booking/Filament/Resources/AdminBookingInterventionResource.php` — extends `Filament\Resources\Resource`, `protected static ?string $model = Booking::class`, `canCreate() = false`, `canViewAny()` gated by `booking.intervene.access`, navigation group `__('admin.nav.groups.booking')`, slug `admin-booking-intervention`, base `getEloquentQuery()` returning bookings matching any of the four trouble buckets per `data-model.md` §"Derived trouble bucket query". Stub `table()` returning empty array — populated in T024. Stub `getPages()` returning `['index' => ListBookingInterventions::route('/'), 'view' => ViewBookingIntervention::route('/{record}')]`.
- [X] T021 [P] Create `app/Modules/Booking/Filament/Resources/AdminBookingInterventionResource/Pages/ListBookingInterventions.php` extending `Filament\Resources\Pages\ListRecords`.
- [X] T022 [P] Create `app/Modules/Booking/Filament/Resources/AdminBookingInterventionResource/Pages/ViewBookingIntervention.php` extending `Filament\Resources\Pages\ViewRecord` with a stub `infolist()` (populated in US2).

### Translations

- [X] T023 [P] Add EN + AR translation keys under `booking.intervention.*` in both `app/Modules/Booking/Resources/lang/en/booking.php` and `app/Modules/Booking/Resources/lang/ar/booking.php`. Cover: nav label, table columns, four trouble badges, six Action labels, six confirmation modal copy blocks, success/error toasts, throttled-message text.

### Architecture tests (constitution principle VII — enforce hard boundary first)

- [X] T024 [P] Create `tests/Architecture/AdminCannotAssignReplacementVendorTest.php` — implements FR-EXT-011. Asserts: (a) no class under `App\Modules\Booking` or `App\Modules\Discovery` whose name matches `/AssignReplacement/i`; (b) no method on Booking Actions matches `/assignReplacementVendor/i`; (c) `Spatie\Permission\Models\Permission::query()->where('name','LIKE','%assign_replacement%')->doesntExist()`; (d) no Filament `Action::make('assign_replacement_vendor')` is registered.
- [X] T025 [P] Create `tests/Architecture/VendorProposalInterventionHasNullProposedVendorTest.php` — asserts every `booking_admin_interventions` row with `intervention_type = 'vendor_proposal'` has `proposed_vendor_id IS NULL` (DB query against seeded fixtures + policy assertion via Gate).
- [X] T026 [P] Create `tests/Architecture/EventsFireAfterCommitTest.php` — greps every Action under `app/Modules/Booking/Application/Actions/Send*`, `Escalate*`, `Suggest*`, `Freeze*`, `Resume*`, `Create*` and asserts every `event(new ` call is lexically enclosed by a `DB::afterCommit` closure.
- [X] T027 [P] Confirm existing `tests/Architecture/NoCrossModuleModelImportsTest.php` and `tests/Architecture/AppendOnlyTablesHaveNoSoftDeletesTest.php` still pass; if they don't recognise the new module paths, add coverage entries.

**Checkpoint**: Foundation green. Architecture tests T024–T026 must already pass before any user-story code lands.

---

## Phase 3: User Story 1 — Surface stalled and late-response bookings (P1) 🎯 MVP

**Goal**: Admins can open a single page that lists every booking in trouble across the four buckets, filterable and sortable, with badges and money/locale rendering.

**Independent Test**: Seed 4 bookings (one per bucket × 3 product types = 12 rows). Visit the page as an admin with `booking.intervene.access`. Verify every booking appears exactly once with the correct badge and the page renders ≤ 1.5 s.

### Tests for US1 (write first; expect to fail until implementation lands)

- [X] T028 [P] [US1] Create `tests/Feature/Modules/Booking/AdminIntervention/AdminBookingInterventionAuthorizationTest.php` covering: (a) unauthenticated → redirect/401; (b) admin without `booking.intervene.access` → 403/no-nav; (c) admin with permission → page renders.
- [X] T029 [P] [US1] Create `tests/Feature/Modules/Booking/AdminIntervention/AdminBookingInterventionPageTest.php` covering: list contains rows for each bucket; filter by trouble-type narrows correctly; default sort is nearest-deadline ASC; pagination defaults to 25; money column renders as `EGP X,XXX.XX`. Include `->group('booking','intervention')`.
- [X] T030 [P] [US1] Per-product-type triplet — extend T029 with `it('lists rental troubled bookings')`, `it('lists sale troubled bookings')`, `it('lists digital troubled bookings')`, each `->group('rental'|'sale'|'digital')`.
- [X] T031 [P] [US1] Locale test — `tests/Feature/Modules/Booking/AdminIntervention/AdminBookingInterventionLocaleTest.php` switches `app()->setLocale('ar')` and asserts AR translation keys render (column labels, badges).

### Factory states (test fixtures)

- [X] T032 [P] [US1] Extend `app/Modules/Booking/Database/Factories/BookingFactory.php` with four state methods: `->stalledWithLateVendor()`, `->withAllVendorsRejected()`, `->withCustomerReviewPending()`, `->stalled()`. Each composes the existing `BookingVendorFactory` / `BookingModificationFactory` per `research.md` §R-10.

### Implementation

- [X] T033 [US1] Implement `AdminBookingInterventionResource::getEloquentQuery()` in `app/Modules/Booking/Filament/Resources/AdminBookingInterventionResource.php` per `data-model.md` §"Derived trouble bucket query". Use `selectSub` for the four boolean flags and a `whereRaw` outer filter; exclude `completed` / `cancelled`.
- [X] T034 [US1] Implement `AdminBookingInterventionResource::table()` columns: reference, customer name, product-type badge (per-type colors per `.claude/rules/filament-components.md` §2), lifecycle/payment/fulfillment status badges, trouble badge(s) (computed from the four boolean flags — show highest-severity first), nearest deadline, total via `->money('EGP', divideBy: 100)`. Add `SelectFilter` for trouble bucket, product_type, governorate, deadline window. Default sort: nearest deadline ASC. Pagination 25/50/100.
- [X] T035 [US1] Run `php artisan shield:generate --all` and re-run `T028`–`T031` until green.

**Checkpoint**: US1 ships independently — admin can monitor without acting.

---

## Phase 4: User Story 2 — Inspect a troubled booking in one place (P1)

**Goal**: Read-only detail page consolidates booking summary, vendors, modifications, state transitions, payments, customer notes, and prior interventions.

**Independent Test**: Open the detail view for a booking with two vendors, one open modification, two prior state transitions, one prior intervention. Verify all seven sections render read-only with correct money formatting and AR locale.

### Tests for US2

- [X] T036 [P] [US2] Create `tests/Feature/Modules/Booking/AdminIntervention/AdminBookingInterventionDetailTest.php` covering: all seven sections render; no edit affordance present; total renders in EGP; AR locale renders vendor business name in Arabic.

### Implementation

- [X] T037 [US2] Populate the infolist in `app/Modules/Booking/Filament/Resources/AdminBookingInterventionResource/Pages/ViewBookingIntervention.php` with seven `Section` blocks: Booking summary (reference, customer, status badges, total, event window), Vendors (`RepeatableEntry` over `vendors` relation showing sub-status, deadline, responded_at, rejection_reason), Open modifications (`RepeatableEntry` over `pendingModifications` scope), State transitions (latest 20 via a scope-bound `RepeatableEntry`), Payments (`RepeatableEntry` showing status + amount), Customer notes (`RepeatableEntry`), Intervention history (`RepeatableEntry` over `adminInterventions` relation).
- [X] T038 [US2] Add the `pendingModifications` and `adminInterventions` scopes/relations on `app/Modules/Booking/Domain/Models/Booking.php` (relations only — no business logic per constitution principle I.3).
- [X] T039 [US2] Re-run T036; ensure green.

**Checkpoint**: US1 + US2 together = the read-only monitoring surface is complete.

---

## Phase 5: User Story 3 — Send a vendor reminder (P1)

**Goal**: A single click queues a reminder dispatch, records an intervention row, appends an audit log, throttled by 5-min idempotency.

**Independent Test**: Click reminder on a `pending` vendor row → `notification_dispatches` row appears, `booking_admin_interventions` row appears, `audit_logs` row appears. Second click within 5 min → throttled error, no duplicates.

### Tests for US3

- [X] T040 [P] [US3] Create `tests/Feature/Modules/Booking/AdminIntervention/SendVendorReminderActionTest.php` covering: happy path (intervention + dispatch + audit_log all present); guard rejects `accepted` or `rejected` sub_status; throttle returns `\DomainException` on second call within 5 min; `Event::assertDispatched(VendorReminderSent::class)` after commit only. `->group('booking','intervention')`.

### Implementation

- [X] T041 [P] [US3] Create event `app/Modules/Booking/Domain/Events/VendorReminderSent.php` per `contracts/events.md`.
- [X] T042 [US3] Create Action `app/Modules/Booking/Application/Actions/SendVendorReminderAction.php` per `contracts/actions.md` §"SendVendorReminderAction". Wrap in `DB::transaction`; throttle via `IdempotencyService::wrap` with scope `admin.intervention.vendor_reminder`; dispatch via `NotificationDispatcher` injected through constructor; event fires inside `DB::afterCommit`.
- [X] T043 [US3] Add Filament row Action `sendVendorReminder` to `AdminBookingInterventionResource::table()`. Visible only when (a) admin has `booking.intervene.send_vendor_reminder` AND (b) the row has at least one `BookingVendor` with `sub_status = pending` AND (c) deadline still in future. Action form: optional `Textarea` note (0..1000 chars). Action body delegates to `app(SendVendorReminderAction::class)->execute(...)`.
- [X] T044 [US3] Run T040 until green.

**Checkpoint**: US3 ships independently — reminders work without escalation/suggestion infra.

---

## Phase 6: User Story 4 — Escalate a late vendor response (P1)

**Goal**: One-click escalation flips `BookingVendor.sub_status` → `timed_out`, writes a state transition, fires `BookingVendorTimedOut`, creates an `AdminInboxItem` and dispatches notifications.

**Independent Test**: Seed `BookingVendor` with `response_deadline < now() − 2h`. Escalate. Verify sub_status, `state_transitions` row, intervention row, `admin_inbox_items` row, vendor + customer dispatches, event fired post-commit. Concurrent escalation → second call rejected via row lock.

### Tests for US4

- [X] T045 [P] [US4] Create `tests/Feature/Modules/Booking/AdminIntervention/EscalateLateVendorResponseActionTest.php` covering: happy path (sub_status flip + transition row + intervention + inbox item + dispatch); guard rejects when deadline not yet passed; pessimistic row lock — simulate concurrent escalation, second call throws; lifecycle_status unchanged after escalation; `Event::assertDispatched(BookingVendorTimedOut::class)` fires after commit.

### Implementation

- [X] T046 [P] [US4] Decide reuse-vs-create for the vendor-timeout event per `research.md` §R-4. If absent: create `app/Modules/Booking/Domain/Events/BookingVendorTimedOut.php` per `contracts/events.md`. Document the decision in the commit message.
- [X] T047 [US4] Create Action `app/Modules/Booking/Application/Actions/EscalateLateVendorResponseAction.php` per `contracts/actions.md` §"EscalateLateVendorResponseAction". `lockForUpdate` on the `BookingVendor` row inside the transaction; write `state_transitions` row with `transitionable_type = BookingVendor::class`; create `BookingAdminIntervention(type=VendorTimeout)`; call `AdminInboxWriter::create(...)` with severity `Medium`; event fires inside `DB::afterCommit`.
- [X] T048 [P] [US4] Create listener `app/Modules/Booking/Application/Listeners/OnBookingVendorTimedOutNotifyVendor.php` implementing `ShouldQueue`, dispatching `booking.vendor.timed_out_by_admin` via `NotificationDispatcher`.
- [X] T049 [P] [US4] Create listener `app/Modules/Booking/Application/Listeners/OnBookingVendorTimedOutNotifyCustomer.php` implementing `ShouldQueue`, dispatching `booking.vendor.timed_out_by_admin.customer`.
- [X] T050 [US4] Wire both listeners in `BookingServiceProvider::boot()` against `BookingVendorTimedOut::class` (do NOT double-wire if the listener already exists for the scheduled-timeout path; reuse it).
- [X] T051 [US4] Add Filament row Action `escalateLateVendorResponse` to `AdminBookingInterventionResource::table()`. Visible only when the row has a `BookingVendor` with `sub_status = pending` AND `response_deadline + grace < now()`. Form: required `Textarea` reason (10..1000 chars). Delegate to `EscalateLateVendorResponseAction`.
- [X] T052 [US4] Run T045 until green.

**Checkpoint**: P1 stories (US1–US4) are complete. MVP can ship here.

---

## Phase 7: User Story 5 — Suggest alternative vendors without assigning one (P2)

**Goal**: Curated list of up-to-N candidate vendors is recorded as a `vendor_proposal` intervention; customer keeps final say. Hard boundary: no `booking_vendors` row is created.

**Independent Test**: For a booking whose only vendor rejected, run the candidate query → at least one match shown. Select 2 candidates and submit → one `booking_admin_interventions` row with `proposed_vendor_id = NULL`, `after_state.suggested_vendor_ids = [id1,id2]`. No new `booking_vendors` row created. Customer receives `booking.alternatives.suggested` dispatch.

### Tests for US5

- [X] T053 [P] [US5] Create `tests/Feature/Modules/Booking/AdminIntervention/SuggestAlternativeVendorsActionTest.php` covering: happy path; candidate filter (vendor approved-for-type, governorate-covered, no overlapping availability block, not already on the booking); rejection of empty list; rejection when oversize (> config max); `proposed_vendor_id` always NULL on the resulting intervention; NO `booking_vendors` row created; per-product-type triplet of cases.
- [X] T054 [P] [US5] Create unit test `tests/Unit/Modules/Discovery/EloquentAlternativeVendorFinderTest.php` covering the candidate filter conditions in isolation (no Filament).

### Implementation

- [X] T055 [P] [US5] Implement `AlternativeVendorFinder::findCandidates(...)` and `::validateCandidates(...)` in `app/Modules/Discovery/Infrastructure/Repositories/EloquentAlternativeVendorFinder.php` per `research.md` §R-5 filter list. Use eager joins (`vendor_profiles`, `vendor_approved_product_types`, `vendor_coverage_areas`, `services`) and `whereNotExists` for the availability block check.
- [X] T056 [P] [US5] Create event `app/Modules/Booking/Domain/Events/AdminSuggestedAlternativeVendors.php` per `contracts/events.md`.
- [X] T057 [US5] Create Action `app/Modules/Booking/Application/Actions/SuggestAlternativeVendorsAction.php` per `contracts/actions.md`. Persist exactly one `BookingAdminIntervention(type=VendorProposal, proposed_vendor_id=NULL, after_state={suggested_vendor_ids, reason})`. Audit log `booking.suggest_alternatives`. Event fires in `DB::afterCommit`. MUST NOT touch `booking_vendors`.
- [X] T058 [P] [US5] Create listener `app/Modules/Booking/Application/Listeners/OnAdminSuggestedAlternativeVendorsNotifyCustomer.php` (`ShouldQueue`), dispatches `booking.alternatives.suggested` with candidate vendors' `public_id`s in the payload (never internal IDs).
- [X] T059 [US5] Add Filament row Action `suggestAlternativeVendors` to `AdminBookingInterventionResource::table()`. Form: `Select` (multiple) populated by `AlternativeVendorFinder::findCandidates(...)` showing vendor `business_name` (translatable) + rating; required `Textarea` reason. Cap selection at `config('booking.intervention.suggest_max_candidates', 5)`. Empty-candidate-list short-circuits with a localized toast.
- [X] T060 [US5] Run T053 + T054 until green. Run `tests/Architecture/AdminCannotAssignReplacementVendorTest.php` to reconfirm the hard boundary.

**Checkpoint**: US5 ships. The page now records advisory suggestions without crossing the marketplace boundary.

---

## Phase 8: User Story 6 — Freeze or resume the booking conversation (P2)

**Goal**: Admin can freeze a chat thread, push the Firestore mirror signal, dispatch notifications; symmetric resume action.

**⚠️ Cut-list gate (per T004)**: Skip entire phase if `chat_threads` base table is not yet migrated. File a follow-up issue and proceed to Phase 9.

**Independent Test**: Seed `chat_threads` row for a booking. Freeze with reason → `frozen_at` set, `frozen_by` = admin.id, Firestore listener fires, both-party dispatches enqueued. Resume → inverse. Double freeze → guard error.

### Tests for US6 (gated)

- [X] T061 [P] [US6] Create `tests/Feature/Modules/Booking/AdminIntervention/FreezeBookingChatActionTest.php` covering: happy path (columns set + intervention + audit + dispatch); guard rejects when already frozen; missing thread fails with localized message.
- [X] T062 [P] [US6] Create `tests/Feature/Modules/Booking/AdminIntervention/ResumeBookingChatActionTest.php` covering: happy path; guard rejects when not currently frozen.

### Implementation (gated)

- [X] T063 [US6] Add migration `app/Modules/Communication/Database/Migrations/2026_05_16_100003_add_frozen_to_chat_threads.php` adding nullable `frozen_at TIMESTAMP NULL`, `frozen_by BIGINT UNSIGNED NULL` FK→`users.id` `nullOnDelete()`, plus composite index `(booking_id, frozen_at)`.
- [X] T064 [P] [US6] Update `app/Modules/Communication/Domain/Models/ChatThread.php` (or create thin model if missing per `data-model.md` §`chat_threads`) — add `frozen_at` + `frozen_by` to `$fillable` and `$casts`.
- [X] T065 [P] [US6] Create event `app/Modules/Booking/Domain/Events/BookingChatFrozen.php`.
- [X] T066 [P] [US6] Create event `app/Modules/Booking/Domain/Events/BookingChatResumed.php`.
- [X] T067 [US6] Create Action `app/Modules/Booking/Application/Actions/FreezeBookingChatAction.php` per `contracts/actions.md`. `lockForUpdate` on the `chat_threads` row inside the transaction.
- [X] T068 [US6] Create Action `app/Modules/Booking/Application/Actions/ResumeBookingChatAction.php` (symmetric).
- [X] T069 [P] [US6] Create listener `app/Modules/Booking/Application/Listeners/OnBookingChatFrozenPushFirestore.php` — `ShouldQueue`, calls existing Firestore chat gateway contract.
- [X] T070 [P] [US6] Create listener `app/Modules/Booking/Application/Listeners/OnBookingChatFrozenNotifyParties.php` — dispatches `booking.chat.frozen` to customer + each vendor user.
- [X] T071 [P] [US6] Create symmetric resume listeners.
- [X] T072 [US6] Add Filament row Actions `freezeBookingChat` + `resumeBookingChat` to `AdminBookingInterventionResource::table()`. Visibility flips on `chat_threads.frozen_at` state.
- [X] T073 [US6] Run T061 + T062 until green.

**Checkpoint**: US6 ships when `chat_threads` exists. Otherwise deferred.

---

## Phase 9: User Story 7 — Resume customer review + admin intervention note (P2)

**Goal**: Reminder action re-pings stalled customer reviews (throttled 4 h); note action records free-text context without changing state.

**Independent Test**: For a booking in `customer_review` with open modification > 24 h, run resume-review → customer dispatch + intervention + audit log; no state changes. Within 4 h → throttled. Note action: submit 200-char message → intervention row, audit log, nothing else.

### Tests for US7

- [X] T074 [P] [US7] Create `tests/Feature/Modules/Booking/AdminIntervention/ResumeBookingReviewActionTest.php` covering: happy path; guard rejects when no open modification; throttle returns `\DomainException` within cooldown; no state mutation on any of `bookings`, `booking_vendors`, `booking_modifications`.
- [X] T075 [P] [US7] Create `tests/Feature/Modules/Booking/AdminIntervention/CreateAdminInterventionNoteActionTest.php` covering: happy path persists intervention + audit log; size validation (1..2000); NO dispatch row created; NO state mutation.

### Implementation

- [X] T076 [P] [US7] Create event `app/Modules/Booking/Domain/Events/CustomerReviewReminderSent.php`.
- [X] T077 [US7] Create Action `app/Modules/Booking/Application/Actions/ResumeBookingReviewAction.php` per `contracts/actions.md`. Throttle via `IdempotencyService` scope `admin.intervention.customer_review_reminder`, key `booking->id`, TTL from config.
- [X] T078 [P] [US7] Create listener `app/Modules/Booking/Application/Listeners/OnCustomerReviewReminderSentNotifyCustomer.php` (`ShouldQueue`).
- [X] T079 [US7] Create Action `app/Modules/Booking/Application/Actions/CreateAdminInterventionNoteAction.php` per `contracts/actions.md`. NO events, NO dispatches.
- [X] T080 [US7] Add Filament row Actions `resumeCustomerReview` + `createInterventionNote` to `AdminBookingInterventionResource::table()`. `resumeCustomerReview` visible only when booking has `lifecycle_status = customer_review` + open modification > 24 h. `createInterventionNote` visible whenever admin holds `booking.intervene.create_note`.
- [X] T081 [US7] Run T074 + T075 until green.

**Checkpoint**: All seven user stories shipped (US6 gated on `chat_threads`).

---

## Phase 10: Polish & Cross-Cutting Concerns

- [ ] T082 [P] Run full Pest suite: `.\vendor\bin\pest --parallel --group=booking,intervention`. All green.
- [ ] T083 [P] Run all four architecture tests: `.\vendor\bin\pest tests/Architecture/`. All green.
- [ ] T084 [P] `php artisan pint` clean across all touched files.
- [ ] T085 [P] `./vendor/bin/phpstan analyse` — no new errors versus baseline.
- [ ] T086 [P] `php artisan shield:generate --all` (idempotent) and verify nav appears for the admin role only.
- [ ] T087 [P] AR locale UI walkthrough per `quickstart.md` §6 — manual; capture one screenshot for the PR.
- [ ] T088 [P] Update `.specify/memory/project-index.md` "Current Phase" line if this becomes the next active feature.
- [ ] T089 [P] Backfill PRs: open issues against `01_PRD.md` §11 and `09_Phasing_Plan.md` Phase 6/7 referencing this feature directory (carries the ⚠️ flags forward).
- [ ] T090 Run the quickstart end-to-end (`quickstart.md` §3–7) against a fresh local DB. Confirm every step passes.
- [ ] T091 Conventional commit per `.claude/settings.json`: `feat(Booking): admin booking intervention page (029) — 6 actions, hard boundary on replacement vendor`.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: no deps
- **Phase 2 (Foundational)**: T007 → T008 (gated by T005); T009 ⟶ T010 ⟶ T020 (DTO must extend before Action signatures rely on it); T012–T015 [P]; T016 → T017 → T018; T019 → T020 → T021/T022; T024–T026 must pass before any user story
- **Phase 3 (US1)**: depends on Phase 2 complete
- **Phase 4 (US2)**: depends on Phase 3 (uses the same Resource)
- **Phase 5 (US3)**, **Phase 6 (US4)**, **Phase 7 (US5)**, **Phase 8 (US6 — gated)**, **Phase 9 (US7)**: all depend on Phase 2 + Phase 3; can run in parallel between developers
- **Phase 10 (Polish)**: depends on all desired user stories

### Within Each User Story

- Tests authored before / alongside implementation per constitution principle VII
- Models / events before Actions
- Actions before Filament wiring
- Listeners can land in parallel with Actions (different files)
- Story green via its own Pest group before moving to the next

### Parallel Opportunities

- **Phase 2**: T009 + T010 + T011 + T012/T013 + T014/T015 + T016 + T017 + T019 + T021 + T022 + T023 + T024–T027 — high parallelism (different files)
- **Phase 3 tests**: T028 + T029 + T030 + T031 + T032 — five parallel files
- **Phase 5 (US3)**: T041 + T040 in parallel; T042 sequential after T041; T043 sequential after T042
- **Phase 6 (US4)**: T045 + T046 + T048 + T049 parallel; T047 sequential; T050 sequential after listeners
- **Phase 7 (US5)**: T053 + T054 + T055 + T056 + T058 parallel; T057 sequential; T059 sequential
- **Phase 8 (US6)**: T061 + T062 + T064 + T065 + T066 + T069 + T070 + T071 parallel; T063 sequential first; T067 + T068 sequential after T064; T072 sequential after Actions
- **Phase 9 (US7)**: T074 + T075 + T076 + T078 parallel; T077 + T079 sequential; T080 last
- **Phase 10**: T082–T089 [P]; T090 + T091 sequential

### Parallel Example — Phase 5 (US3)

```text
# In parallel:
Task: T040 — write SendVendorReminderActionTest.php
Task: T041 — create VendorReminderSent.php event
# Then sequential:
Task: T042 — create SendVendorReminderAction.php
Task: T043 — wire Filament row action
Task: T044 — make tests green
```

---

## Implementation Strategy

### MVP first (US1 + US3 + US4 only — minimum useful triage tool)

1. Complete Phase 1 + Phase 2
2. Complete Phase 3 (US1 — the list)
3. Complete Phase 5 (US3 — reminder)
4. Complete Phase 6 (US4 — escalation)
5. **STOP and validate**: an admin can see and unblock late vendors
6. Deploy/demo

### Incremental delivery

1. MVP above (3 P1 stories shippable in ~2–3 days)
2. Add US2 (detail infolist) — half-day
3. Add US5 (suggestions) — 1 day (Discovery contract is the long pole)
4. Add US7 (resume review + note) — half-day
5. Add US6 (chat freeze) — gated on `chat_threads`; ~1 day once unblocked

### Parallel team strategy

Two developers can split P1 work after Foundational:

- Dev A: US1 + US2 (Filament-heavy, single resource focus)
- Dev B: US3 + US4 (Actions + listeners + dispatches)

Both meet at Phase 10 polish.

---

## Notes

- `[P]` tasks always touch different files
- Story labels enable parallel team work and per-story release gates
- Tests written same day as code (no "test day" slip per constitution Daily Discipline §2)
- Commit at every checkpoint with the conventional-commit format from `.claude/settings.json`
- US6 may ship in a separate follow-up PR if `chat_threads` table is not yet migrated when this PR opens
- Architecture tests T024 / T025 / T026 are non-negotiable gates — they enforce FR-EXT-010 / FR-EXT-011 / FR-EXT-012 and constitution principle IX
