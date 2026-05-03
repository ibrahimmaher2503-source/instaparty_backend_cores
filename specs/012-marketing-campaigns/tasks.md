# Tasks: Marketing Campaigns (Phase 5.3)

**Input**: `specs/012-marketing-campaigns/plan.md` + `specs/012-marketing-campaigns/spec.md`
**Phase**: 5.3 — 1 day, Week 7
**ADR**: ADR-0010 (Communication Module — no new ADR)
**PRD**: FR-23 (push), FR-24 (WhatsApp), FR-25 (SMS), FR-26 (email), FR-27 (cost separation)
**Tables**: `campaigns`, `campaign_runs`, `campaign_recipients` (new) + reads from Phase 5.0 tables

**User Stories** (from spec.md):
- **US1** (P1): Admin builds and sends a segmented marketing campaign
- **US2** (P1): Marketing opt-outs are respected
- **US3** (P1): Segment resolver matches customers by booking history and product type
- **US4** (P1): All four channels dispatch correctly
- **US5** (P2): Campaign run is observable from Filament

**Tests**: Included — spec.md §Requirements calls for Pest coverage per user story (FR-5.3.07, FR-5.3.10, FR-5.3.11, FR-5.3.18, FR-5.3.23) and Constitution §VII mandates test-first for critical paths.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no shared dependencies in this batch)
- **[Story]**: Which user story this task belongs to
- Every task includes an exact file path

---

## Phase 1: Setup (Module Registration)

**Purpose**: Wire the three new campaign tables into the existing Communication module's service provider. Phase 5.0 already created `CommunicationServiceProvider`; this phase extends it.

**⚠️ NOTE**: `app/Modules/Communication/` already exists. No new module scaffold needed.

- [ ] T001 Register the three campaign migrations in `app/Modules/Communication/Providers/CommunicationServiceProvider.php` by confirming `loadMigrationsFrom()` covers the existing `Database/Migrations/` folder (no separate registration needed if migrations land there — verify and document).

**Checkpoint**: Service provider registration confirmed — migrations will auto-load.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Schema, enums, models, and cross-module contracts. Every user story depends on these. No user story work until this phase is complete.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

### Migrations

- [ ] T002 Create migration `app/Modules/Communication/Database/Migrations/2026_05_07_100001_create_campaigns_table.php` — `campaigns` table per plan.md §3: `public_id CHAR(26) UNIQUE`, `name VARCHAR(160)`, `channel ENUM('push','sms','whatsapp','email')`, `target_locale ENUM('ar','en','both')`, `segment_filters JSON NOT NULL`, `product_type_segment JSON NULL`, `subject JSON NULL`, `body JSON NOT NULL`, `scheduled_at TIMESTAMP NULL`, `status ENUM('draft','scheduled','running','completed','failed','cancelled') DEFAULT 'draft'`, `created_by FK→users RESTRICT`, indexes `(status, created_at)` + `(channel, status)` + `(created_by, status)`, charset utf8mb4
- [ ] T003 Create migration `app/Modules/Communication/Database/Migrations/2026_05_07_100002_create_campaign_runs_table.php` — `campaign_runs` per plan.md §3: `campaign_id FK→campaigns RESTRICT`, `started_at TIMESTAMP NULL`, `completed_at TIMESTAMP NULL`, `recipients_total/sent/failed INT UNSIGNED DEFAULT 0`, index `(campaign_id, started_at)`, timestamps
- [ ] T004 Create migration `app/Modules/Communication/Database/Migrations/2026_05_07_100003_create_campaign_recipients_table.php` — `campaign_recipients` per plan.md §3: `campaign_run_id FK→campaign_runs CASCADE`, `user_id FK→users RESTRICT`, `dispatch_id FK→notification_dispatches RESTRICT NULL`, `status ENUM('queued','sent','failed','skipped') DEFAULT 'queued'`, `created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP` (no updated_at), UNIQUE `(campaign_run_id, user_id)`, index `(campaign_run_id, status)`, index `(user_id, created_at)`

### Enums

- [ ] T005 [P] Create `app/Modules/Communication/Domain/Enums/CampaignStatus.php` — cases: `Draft`, `Scheduled`, `Running`, `Completed`, `Failed`, `Cancelled`; backed string enum
- [ ] T006 [P] Create `app/Modules/Communication/Domain/Enums/CampaignChannel.php` — cases: `Push`, `Sms`, `WhatsApp`, `Email`; backed string enum with `value` matching DB ENUM (`'push'`, `'sms'`, `'whatsapp'`, `'email'`)
- [ ] T007 [P] Create `app/Modules/Communication/Domain/Enums/CampaignTargetLocale.php` — cases: `Ar`, `En`, `Both`; backed string enum
- [ ] T008 [P] Create `app/Modules/Communication/Domain\Enums/CampaignRecipientStatus.php` — cases: `Queued`, `Sent`, `Failed`, `Skipped`; backed string enum

### Models

- [ ] T009 [P] Create `app/Modules/Communication/Domain/Models/Campaign.php` — Eloquent model: `$translatable = ['subject', 'body']`, casts `channel → CampaignChannel`, `target_locale → CampaignTargetLocale`, `status → CampaignStatus`, `segment_filters → array`, `product_type_segment → array`; scopes `scopeDraft`, `scopeRunning`, `scopeCompleted`; relationships `hasMany(CampaignRun)`, `belongsTo(User, 'created_by')`; no soft deletes; `$guarded = []`
- [ ] T010 [P] Create `app/Modules/Communication/Domain/Models/CampaignRun.php` — relationships: `belongsTo(Campaign)`, `hasMany(CampaignRecipient)`; timestamps; no soft deletes
- [ ] T011 [P] Create `app/Modules/Communication/Domain/Models/CampaignRecipient.php` — casts `status → CampaignRecipientStatus`; relationships: `belongsTo(CampaignRun)`, `belongsTo(User)`, `belongsTo(NotificationDispatch, 'dispatch_id')` (nullable); `created_at` only (`$timestamps = false`, set `created_at` manually)

### Cross-module Contracts

- [ ] T012 Create contract `app/Modules/Booking/Domain/Contracts/BookingHistoryReader.php` — interface with method `customersWithBookingsMatching(?ProductType $productType, ?int $withinDays, ?int $governorateId, ?string $preferredLocale): Collection` (returns Collection of user_id integers, deduplicated, excludes banned/suspended users)
- [ ] T013 Create Eloquent implementation `app/Modules/Booking/Infrastructure/Repositories/EloquentBookingHistoryReader.php` — joins `bookings` + `booking_items` + `users` + `customer_profiles`; applies filters as nullable wheres; excludes `users.status IN ('suspended','banned')`; filters `customer_profiles` (audience = customer only); returns `pluck('user_id')->unique()->values()`
- [ ] T014 Bind `BookingHistoryReader` → `EloquentBookingHistoryReader` in `app/Modules/Booking/Providers/BookingServiceProvider.php` `register()` method
- [ ] T015 Verify `Geography\Domain\Contracts\GovernorateReader` exists (from Phase 0) for `governorate_id` filter validation — if missing, create stub interface in `app/Modules/Geography/Domain/Contracts/GovernorateReader.php` with `exists(int $id): bool`

**Checkpoint**: All migrations, models, enums, and contracts complete — user story implementation can now begin.

---

## Phase 3: User Story 1 — Admin Builds and Sends a Segmented Campaign (P1) 🎯 MVP

**Goal**: Admin opens Filament Campaign Builder, fills form, clicks "Send now" → `campaigns` row created, `campaign_runs` row created, `campaign_recipients` rows written in `queued` state, jobs enqueued.

**Independent Test**: Seed 10 customers (4 with rental bookings in 30d, 6 without), create a campaign via `CreateCampaignAction`, dispatch via `DispatchCampaignAction`, assert exactly 4 `campaign_recipients` rows exist for the run, `campaigns.status = 'running'`, one `campaign_runs` row exists — before any queue worker runs.

### Tests for US1 ⚠️ Write first — must FAIL before implementation

- [ ] T016 [P] [US1] Write failing Pest test `tests/Feature/Modules/Communication/Campaigns/CreateCampaignActionTest.php` — covers: `CreateCampaignAction` happy path (draft row written), missing EN body → exception, missing AR body → exception, empty `segment_filters` → exception
- [ ] T017 [P] [US1] Write failing Pest test `tests/Feature/Modules/Communication/Campaigns/DispatchCampaignActionTest.php` — covers: `DispatchCampaignAction` happy path (status flips to `running`, run row created, correct count of recipient rows written in `queued` state, jobs queued via `DB::afterCommit`)

### Implementation for US1

- [ ] T018 [US1] Create DTO `app/Modules/Communication/Application/DTOs/BuildCampaignDTO.php` — typed properties: `string $name`, `CampaignChannel $channel`, `CampaignTargetLocale $targetLocale`, `array $segmentFilters`, `?array $productTypeSegment`, `array $subject` (locale→text), `array $body` (locale→text), `int $createdBy`
- [ ] T019 [US1] Create `app/Modules/Communication/Application/Actions/CreateCampaignAction.php` — constructor-injected repository; `execute(BuildCampaignDTO $dto): Campaign`; validates segment_filters keys against whitelist (`booked_product_type`, `booked_within_days`, `governorate_id`, `preferred_locale`) — throw `InvalidSegmentFilterException` for unknown keys; validates body EN + AR both non-empty; wrapped in `DB::transaction`
- [ ] T020 [US1] Create `app/Modules/Communication/Application/Actions/DispatchCampaignAction.php` — `execute(Campaign $campaign): CampaignRun`; guards status (`draft` only, else throw `CampaignAlreadyDispatchedException`); within `DB::transaction`: flip `campaigns.status = running`, insert `campaign_runs` row with `recipients_total = 0` (updated after resolution), call `SegmentResolver::resolve()`, bulk-insert `campaign_recipients` rows (status `queued`), update `campaign_runs.recipients_total`, then `DB::afterCommit(fn () => Bus::batch(...)->dispatch())` for per-recipient jobs
- [ ] T021 [US1] Create `app/Modules/Communication/Application/Actions/CancelCampaignAction.php` — `execute(Campaign $campaign): void`; `draft` → delete; `running` → update `status = cancelled`; anything else → throw `CampaignCannotBeCancelledException`; wrapped in `DB::transaction`
- [ ] T022 [US1] Create Filament resource `app/Modules/Communication/Filament/Resources/CampaignResource.php` — navigation group `'Communications'`, navigation label `'Campaigns'`; uses `Translatable` trait + `getTranslatableLocales() = ['en','ar']`; form: `TextInput::make('name')`, `Select::make('channel')->options(CampaignChannel::class)`, `Select::make('target_locale')->options(CampaignTargetLocale::class)`, EN/AR tabs for `subject` + `body` (Textarea), `KeyValue::make('segment_filters')` with helper text listing allowed keys; table: `TextColumn::make('name')`, `TextColumn::make('channel')->badge()`, `TextColumn::make('status')->badge()->color(...)`, `TextColumn::make('created_at')->dateTime()->sortable()`; filter by `status`, `channel`
- [ ] T023 [US1] Add "Send Now" Filament table action on `CampaignResource` — `Action::make('sendNow')->requiresConfirmation()->visible(fn (Campaign $r) => $r->status === CampaignStatus::Draft)->action(fn (Campaign $r) => app(DispatchCampaignAction::class)->execute($r))->requiresPermission('dispatch_campaign')` → success `Notification::make()->title('Campaign dispatched')->success()->send()` or `->danger()` on exception
- [ ] T024 [US1] Add "Cancel" Filament action on `CampaignResource` calling `CancelCampaignAction::execute()`; visible for `draft` and `running` states
- [ ] T025 [US1] Run T016/T017 tests — verify they now pass

**Checkpoint**: Admin can create and dispatch a campaign. Recipient rows written. Jobs queued. `campaigns.status = 'running'`.

---

## Phase 4: User Story 2 — Marketing Opt-outs Are Respected (P1)

**Goal**: Customers with `notification_preferences (channel, 'marketing') is_enabled = false` are recorded as `skipped` — no provider call, no `notification_dispatches` row written for them.

**Independent Test**: Create one customer with `notification_preferences (push, marketing, is_enabled = false)` matching the segment. Run the campaign. Assert that customer's `campaign_recipients.status = 'skipped'`, `dispatch_id = NULL`, and mocked adapter was NOT invoked.

### Tests for US2 ⚠️ Write first

- [ ] T026 [P] [US2] Write failing Pest test `tests/Feature/Modules/Communication/Campaigns/OptOutEnforcementTest.php` — covers: opted-out recipient → `status = 'skipped'`, `dispatch_id = NULL`; absent preference row → dispatched normally (default-enabled); per-channel × category isolation (email opt-out does not affect sms campaign); `event_category` locked to `'marketing'` (not overridable)

### Implementation for US2

- [ ] T027 [US2] Create `app/Modules/Communication/Application/Jobs/DispatchCampaignRecipientJob.php` — `implements ShouldQueue`; constructor: `Campaign $campaign`, `int $userId`, `int $campaignRecipientId`; `handle()`: load user, check `notification_preferences (channel, 'marketing')` via a repository query (absent row = enabled); if opted out → `CampaignRecipient::find($id)->update(['status' => CampaignRecipientStatus::Skipped])`, return; else → call `DispatchNotificationAction::execute(...)` with the campaign body for `$user->preferred_locale`, on success → update `campaign_recipients` row `status = sent, dispatch_id = $dispatch->id`; on failure → `status = failed, error_message = $e->getMessage()`; always call `CompleteCampaignRunAction::checkAndComplete($campaignRun)` after update; wrapped in `DB::transaction`
- [ ] T028 [US2] Run T026 tests — verify they now pass

**Checkpoint**: Opted-out customers are skipped. Mocked adapter assertions pass. Per-category isolation confirmed.

---

## Phase 5: User Story 3 — Segment Resolver (P1)

**Goal**: `SegmentResolver` returns the correct, deduplicated set of `user_id`s for each supported filter combination (`booked_product_type`, `booked_within_days`, `governorate_id`, `preferred_locale`), excluding banned/suspended users and restricting to customers.

**Independent Test**: Seed customers with varied booking histories, call `SegmentResolver::resolve()` directly with each of the five filter combos from plan.md §4, assert the returned `user_id` set matches exactly.

### Tests for US3 ⚠️ Write first

- [ ] T029 [P] [US3] Write failing Pest tests `tests/Feature/Modules/Communication/Campaigns/SegmentResolverTest.php`:
  - Rental within 30 days filter → correct subset (group: `rental`)
  - Sale within 30 days filter → correct subset (group: `sale`)
  - Digital within 30 days filter → correct subset (group: `digital`)
  - No product type filter → all types within window
  - `governorate_id` + product type filter → intersected result
  - Customer with two qualifying bookings → appears once (deduplication)
  - Suspended/banned user → excluded even if booking matches
  - Vendor user → excluded (only `customer_profiles` rows included)
  - Empty `segment_filters` → `InvalidSegmentFilterException` thrown
  - Unknown filter key → `InvalidSegmentFilterException` thrown

### Implementation for US3

- [ ] T030 [US3] Create `app/Modules/Communication/Application/Services/SegmentResolver.php` — constructor-injects `BookingHistoryReader $reader`; `resolve(array $filters): Collection`; validates filter keys against whitelist (throw `InvalidSegmentFilterException` if empty or unknown key present); maps `booked_product_type` string → `ProductType::from(...)` enum; calls `$this->reader->customersWithBookingsMatching(...)` (no `if/elseif` on type strings); applies `target_locale` exclusion post-query (if `'ar'` or `'en'`, filter returned user_ids by joining `users.preferred_locale`); returns deduplicated `Collection<int>`
- [ ] T031 [US3] Bind `SegmentResolver` in `CommunicationServiceProvider::register()` (singleton, injecting `BookingHistoryReader` interface)
- [ ] T032 [US3] Run T029 tests — verify they now pass; run per-type Pest groups `--group=rental,sale,digital`

**Checkpoint**: Segment resolver accurate for all five filter combos and all three product types. Deduplication and exclusions confirmed.

---

## Phase 6: User Story 4 — All Four Channels Dispatch Correctly (P1)

**Goal**: Each campaign channel (`push`, `sms`, `whatsapp`, `email`) produces a `notification_dispatches` row with the correct `provider` value by delegating to `DispatchNotificationAction`. No direct adapter calls from the campaign layer.

**Independent Test**: Run four single-recipient campaigns (one per channel). Assert each produces `notification_dispatches` row with correct `channel` + `provider`. Assert `DispatchCampaignRecipientJob` does NOT reference any adapter class directly (architecture test).

### Tests for US4 ⚠️ Write first

- [ ] T033 [P] [US4] Write failing Pest tests `tests/Feature/Modules/Communication/Campaigns/ChannelDispatchTest.php`:
  - Push campaign → `notification_dispatches.channel = 'push'`, `provider = 'fcm'` (group: `communication`)
  - SMS campaign → `notification_dispatches.channel = 'sms'`, `provider = 'vonage'` (group: `communication`)
  - WhatsApp campaign → `notification_dispatches.channel = 'whatsapp'`, `provider = 'whatsapp_stub'`, no real API call (group: `communication`)
  - Email campaign → `notification_dispatches.channel = 'email'`, `provider = 'mailchimp'` (group: `communication`)
  - Each: `campaign_recipients.dispatch_id` FK links to correct `notification_dispatches.id`

### Implementation for US4

- [ ] T034 [US4] Create `app/Modules/Communication/Application/Actions/CompleteCampaignRunAction.php` — static method `checkAndComplete(CampaignRun $run): void`; counts `campaign_recipients WHERE campaign_run_id = $run->id AND status IN ('queued')` → if 0 remaining queued rows → `DB::transaction`: update `campaign_runs.completed_at = now()`, update `campaigns.status = completed`; then `DB::afterCommit(fn () => MarketingCampaignDispatched::dispatch(...))` — no `CompleteCampaignRunAction` call if still-queued rows remain
- [ ] T035 [US4] Create domain event `app/Modules/Communication/Domain/Events/MarketingCampaignDispatched.php` — constructor: `int $campaignId, int $campaignRunId, int $recipientsTotal, int $recipientsSent, int $recipientsFailed`; implements `ShouldBroadcast` marker is optional in Phase 1 (event is recorded for future analytics; no listeners required)
- [ ] T036 [US4] Update `DispatchCampaignRecipientJob::handle()` (from T027) to pass the campaign body correctly to `DispatchNotificationAction` — the job calls `DispatchNotificationAction::execute()` with a `DispatchNotificationDTO` carrying: `userId`, `channel`, `locale = $user->preferred_locale`, `body = $campaign->getTranslation('body', $user->preferred_locale)`, `subject = $campaign->getTranslation('subject', $user->preferred_locale)`, `eventCategory = 'marketing'`, `referenceType = Campaign::class`, `referenceId = $campaign->id`; this is the **only** place adapters are invoked (via Phase 5.0's `DispatchNotificationAction`)
- [ ] T037 [US4] Run T033 tests — verify they now pass for all four channels; run `--group=communication`

**Checkpoint**: All four channels produce correct `notification_dispatches` rows. `campaign_recipients.dispatch_id` linked. No direct adapter references in campaign code.

---

## Phase 7: User Story 5 — Campaign Run Observable from Filament (P2)

**Goal**: Admin opens a campaign in Filament and sees recipient counts (`total/sent/failed/skipped`), plus a paginated recipient sub-table with per-row status and linked dispatch info.

**Independent Test**: Seed a completed run with 3 recipients (1 sent, 1 failed, 1 skipped). Open `CampaignResource` view page. Assert `recipients_total = 3`, `recipients_sent = 1`, `recipients_failed = 1`, `skipped = 1` (derived: `total - sent - failed`). Assert recipient sub-table lists all three rows with correct status values.

### Tests for US5 ⚠️ Write first

- [ ] T038 [P] [US5] Write failing Pest test `tests/Feature/Modules/Communication/Campaigns/CampaignObservabilityTest.php` — covers: completed run totals display, skipped count derivation, recipient sub-table rows per status, running state (partial counts visible), recipient row links to `notification_dispatches` (locale + provider + error_message accessible)

### Implementation for US5

- [ ] T039 [US5] Add `ViewCampaign` Filament page to `CampaignResource` — `pages()` returns `['view' => Pages\ViewCampaign::class]`; use `Infolist` for campaign header (name, channel, status badge, created_by, created_at)
- [ ] T040 [US5] Add stats row to `ViewCampaign` — using Filament `Infolists\Components\TextEntry` or a `StatsOverviewWidget` sub-component showing: `recipients_total`, `recipients_sent`, `recipients_failed`, `skipped (derived)` from the latest `campaign_runs` row
- [ ] T041 [US5] Add recipients sub-table to `ViewCampaign` as a `TableWidget` or `RelationManager` — columns: `user.name`, `status` (badge with color: `sent` = success, `failed` = danger, `skipped` = warning, `queued` = gray), `dispatch.locale`, `dispatch.provider`, `dispatch.error_message` (nullable); filter by `status`; paginated 20/page
- [ ] T042 [US5] Run T038 tests — verify they now pass

**Checkpoint**: Admin can observe all recipient counts and drill into each recipient's dispatch status.

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Architecture tests, Filament shield permissions, seeder updates, smoke test.

- [ ] T043 [P] Add Communication campaigns section to `tests/Architecture/NoCrossModuleModelImportsTest.php` — assert `App\Modules\Communication\Application` does not import `App\Modules\Booking\Domain\Models`, `App\Modules\Catalog\Domain\Models`, `App\Modules\Identity\Domain\Models`, `App\Modules\Geography\Domain\Models` (per plan.md §10)
- [ ] T044 [P] Add `SegmentResolver` clause to `tests/Architecture/NoIfElseOnProductTypeStringTest.php` — assert `SegmentResolver` does not contain `if.*product_type.*===` or `elseif.*product_type.*===` (per plan.md §10)
- [ ] T045 [P] Add `CompleteCampaignRunAction` clause to `tests/Architecture/EventAfterCommitTest.php` — assert `CompleteCampaignRunAction` does not use raw `Event::dispatch` (must use `DB::afterCommit`) (per plan.md §10)
- [ ] T046 [P] Add campaign models clause to `tests/Architecture/AppendOnlyTablesHaveNoSoftDeletesTest.php` — assert `Campaign`, `CampaignRun`, `CampaignRecipient` models do not use `SoftDeletes` trait (per plan.md §10)
- [ ] T047 [P] Create new `tests/Architecture/CampaignDispatchUsesPhase50ContractTest.php` — assert `DispatchCampaignRecipientJob` uses `DispatchNotificationAction` and does NOT directly use any of `FcmPushAdapter`, `VonageSmsAdapter`, `WhatsAppStubAdapter`, `MailchimpEmailAdapter` (per plan.md §10, FR-5.3.18)
- [ ] T048 Run `php artisan shield:generate --all` and verify the following permissions exist: `view_any_campaign`, `view_campaign`, `create_campaign`, `update_campaign`, `delete_campaign` — then manually add custom permission `dispatch_campaign` to `config/filament-shield.php` or via seeder
- [ ] T049 Update `RolePermissionsSeeder` (or equivalent permission seeder) to assign `dispatch_campaign` to `super_admin` and `marketing_admin` roles only — run seeder and verify with `php artisan tinker` `Role::findByName('marketing_admin')->permissions->pluck('name')`
- [ ] T050 Run all architecture tests: `./vendor/bin/pest tests/Architecture/` — verify all pass
- [ ] T051 Run full campaign test suite: `./vendor/bin/pest tests/Feature/Modules/Communication/Campaigns/ --group=communication,rental,sale,digital` — verify 0 failures
- [ ] T052 Smoke test on local dev: build "10% off Rentals this week" canonical campaign (per `09_Phasing_Plan.md` deliverable) via Filament admin → `segment_filters = {"booked_product_type": "rental", "booked_within_days": 30}` → verify recipient rows resolve, one opted-out customer shows `status = 'skipped'`, a push recipient shows `notification_dispatches.provider = 'fcm'`, an email recipient shows `provider = 'mailchimp'`, `campaign_runs.completed_at` is set, `campaigns.status = 'completed'`

**Exit Criteria Check** (per `09_Phasing_Plan.md` §PHASE 5.3):
- ✅ Admin builds campaign for specific segment (T019–T025)
- ✅ All 4 channels dispatch (T033–T037)
- ✅ Locale respected per recipient (T030, T036, T052)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1** (Setup): No dependencies — start immediately
- **Phase 2** (Foundational): Depends on Phase 1 — **BLOCKS all user stories**
- **Phase 3** (US1): Depends on Phase 2 complete; US1 delivers end-to-end Create + Dispatch flow
- **Phase 4** (US2): Depends on Phase 2 + Phase 3 job scaffold (T027 refines `DispatchCampaignRecipientJob`)
- **Phase 5** (US3): Depends on Phase 2 (contracts T012–T014); can run in parallel with Phase 4
- **Phase 6** (US4): Depends on Phase 3 (job from T027) + Phase 5 (resolver used in `DispatchCampaignAction`)
- **Phase 7** (US5): Depends on Phase 6 (needs completed runs to display)
- **Phase 8** (Polish): Depends on all phases complete

### User Story Dependencies

- **US1 (P1)**: Depends on Phase 2 — no other story dependency
- **US2 (P1)**: Depends on US1 job scaffold (`DispatchCampaignRecipientJob`) existing; can layer opt-out check on top
- **US3 (P1)**: Depends on Phase 2 contracts only — can develop `SegmentResolver` in parallel with US2
- **US4 (P1)**: Depends on US1 job + US3 resolver (both called during dispatch)
- **US5 (P2)**: Depends on US4 (needs `campaign_recipients` rows populated by completed runs)

### Within Each Phase

- Tests MUST be written first (fail) before implementation code
- Models → DTOs → Actions → Jobs → Events → Filament
- Architecture tests written alongside implementation; run at end of day

---

## Parallel Opportunities

### Phase 2 (can run in parallel after T001)

```
T002 (migration: campaigns)     T005 (enum: CampaignStatus)     T009 (model: Campaign)
T003 (migration: campaign_runs)  T006 (enum: CampaignChannel)    T010 (model: CampaignRun)
T004 (migration: recipients)    T007 (enum: CampaignTargetLocale) T011 (model: CampaignRecipient)
                                T008 (enum: RecipientStatus)
```

Then: T012 + T013 + T015 (contracts) in parallel, T014 after T013.

### Phase 3 (write tests first, then implementation)

```
T016 (CreateCampaignAction test)   T017 (DispatchCampaignAction test)   ← both [P], write in parallel
Then: T018 (DTO) → T019 (CreateAction) → T020 (DispatchAction) → T021 (CancelAction)
T022 (CampaignResource) + T023 (SendNow action) can parallel with T020 after T018 done
```

### Phase 5 + Phase 4 (can run in parallel)

```
Developer A: T029 (SegmentResolver tests) → T030 (SegmentResolver impl) → T031 (bind) → T032 (run)
Developer B: T026 (OptOut tests) → T027 (Job opt-out check) → T028 (run)
```

---

## Implementation Strategy

### MVP (US1 only — end-to-end send without opt-out or segment complexity)

1. Phase 1: Setup
2. Phase 2: Foundational (all migrations, models, enums, contracts)
3. Phase 3: US1 (create + dispatch action + Filament resource + Send Now)
4. **STOP and VALIDATE**: Admin can create and dispatch. Recipient rows appear in DB.

### Full Phase 5.3 (one-day)

Complete phases in order: 1 → 2 → 3 → 4 + 5 (parallel) → 6 → 7 → 8

---

## Task Summary

| Phase | Purpose | Tasks | Parallel |
|---|---|---|---|
| Phase 1 | Setup | T001 | — |
| Phase 2 | Foundational | T002–T015 (14) | T005–T011 in parallel |
| Phase 3 | US1 — Campaign Build + Dispatch | T016–T025 (10) | T016, T017 [P] |
| Phase 4 | US2 — Opt-out Enforcement | T026–T028 (3) | T026 [P] |
| Phase 5 | US3 — Segment Resolver | T029–T032 (4) | T029 [P]; parallel with Phase 4 |
| Phase 6 | US4 — Four Channels | T033–T037 (5) | T033 [P] |
| Phase 7 | US5 — Filament Observability | T038–T042 (5) | T038 [P] |
| Phase 8 | Polish + Arch Tests | T043–T052 (10) | T043–T047 [P] |
| **Total** | | **52 tasks** | |

---

## Notes

- `[P]` tasks operate on different files with no shared state — safe to run in parallel within their phase
- `[Story]` labels enable traceability from test failure → user story → FR number
- Campaign module reuses Phase 5.0's `DispatchNotificationAction` — do NOT replicate adapter calls
- `DispatchCampaignRecipientJob` is the seam between campaign dispatch and Phase 5.0's notification infrastructure
- All money rules are N/A here — no financial columns or ledger entries
- Run `php artisan shield:generate --all` AFTER creating `CampaignResource` (T022), not before
- Architecture tests (T043–T047) catch cross-module model imports and adapter-bypass regressions before they reach PR review
