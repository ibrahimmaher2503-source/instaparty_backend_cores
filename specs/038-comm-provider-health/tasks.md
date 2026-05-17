---
description: "Task list for Communication Provider Health implementation"
---

# Tasks: Communication Provider Health

**Input**: Design documents from `/specs/038-comm-provider-health/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/notification-channel-adapter.md, quickstart.md
**Tests**: Pest tests are required per spec FR-EXT-029…FR-EXT-035 and Constitution §VII. Test tasks are included for each user story.
**Phase alignment**: Phase 5.3 — Communication Provider Health ⚠️ PHASE BACKFILL NEEDED (not yet listed in `docs/specs/09_Phasing_Plan.md`).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: Maps to user stories from spec.md (US1–US4) or SHARED for foundational work
- All paths are absolute under repo root `C:\instaparty_backend_cores\`

## Path Conventions

- Module source: `app/Modules/Communication/`
- Tests: `tests/Feature/Modules/Communication/ProviderHealth/`
- Admin panel registration: `app/Providers/Filament/AdminPanelProvider.php`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Schema extension, permissions, language scaffolding, env-conditional adapter binding — must land before any user story.

- [ ] T001 [SHARED] Create migration `app/Modules/Communication/Database/Migrations/2026_05_17_100001_extend_notification_dispatches_for_provider_health.php` per data-model.md §1. Use `Schema::table('notification_dispatches', ...)` to add 10 nullable columns (`provider_name`, `provider_message_id`, `provider_status`, `provider_error_code`, `provider_error_message`, `attempt_count` defaulting 0, `last_attempt_at`, `next_retry_at`, `is_test` defaulting false, `updated_at`) and 3 new indexes (`idx_dispatches_retry_scan`, `idx_dispatches_provider_name`, `idx_dispatches_is_test`). Do NOT drop legacy columns. Down migration drops the new columns/indexes in reverse order.
- [ ] T002 [SHARED] Run `php artisan migrate` against the dev database to confirm the migration applies cleanly. Verify with `php artisan db:show notification_dispatches` that all 10 new columns + 3 indexes exist.
- [ ] T003 [P] [SHARED] Create `app/Modules/Communication/Database/Seeders/CommunicationProviderHealthPermissionsSeeder.php` that idempotently creates the three new permissions (`view_communication_provider_health`, `send_test_notification`, `retry_notification_dispatch`) and assigns them to the `admin` role.
- [ ] T004 [SHARED] Register `CommunicationProviderHealthPermissionsSeeder` in the existing seeder bootstrap (`database/seeders/DatabaseSeeder.php` or the Communication module's seeder runner) so `php artisan migrate --seed` activates it.
- [ ] T005 [P] [SHARED] Append `provider_health.*` translation keys to `app/Modules/Communication/Resources/lang/en/communication.php`. Keys: page title, navigation label, channel labels (push/sms/whatsapp/email), status labels (reachable/unreachable/unknown/healthy/degraded), card field labels (adapter, latency, sent 24h, failed 24h, last success), `Send Test` button label, modal field labels (device_token, phone_e164, email_address, body_override, subject_override), validation messages, success notifications (`test_queued`, `dispatch_retried`, `retry_cap_reached`), retry action label + confirmation message.
- [ ] T006 [P] [SHARED] Mirror T005 in `app/Modules/Communication/Resources/lang/ar/communication.php` with Arabic translations.
- [ ] T007 [SHARED] Add an entry for `admin.nav.groups.communication` to `lang/en/admin.php` and `lang/ar/admin.php` if not already present (verify — the existing `NotificationTemplateResource` already uses this key, so it likely exists; this task is a verification step).

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Extended contract, value object, model extension, NullProviderAdapter, queued job, adapter updates — every user story consumes these.

**⚠️ CRITICAL**: No user story phase may begin until this phase is complete.

### Contract + value object + exceptions

- [ ] T008 [P] [SHARED] Create `app/Modules/Communication/Domain/ValueObjects/ProviderHealthResult.php` per data-model.md §4 (readonly class with `providerName`, `isReachable`, `latencyMs`, `note`, `checkedAt`; static factories `reachable()` and `unreachable()`; `toArray()` method).
- [ ] T009 [SHARED] Edit `app/Modules/Communication/Domain/Contracts/NotificationChannelAdapter.php` per contracts/notification-channel-adapter.md to add `public function name(): string` and `public function healthCheck(): ProviderHealthResult` methods. Keep the existing `send()` signature unchanged.
- [ ] T010 [P] [SHARED] Create `app/Modules/Communication/Application/Exceptions/DispatchNotRetryableException.php` per data-model.md §6. Constructor accepts `NotificationDispatch $dispatch` and `string $reason`.
- [ ] T011 [P] [SHARED] Create `app/Modules/Communication/Application/Exceptions/NullAdapterInProductionException.php` per data-model.md §6. Default message: "NullProviderAdapter must never be used in production."

### Model + event + job

- [ ] T012 [SHARED] Edit `app/Modules/Communication/Domain/Models/NotificationDispatch.php` per data-model.md §2: flip `$timestamps` to `true`; remove `CREATED_AT`/`UPDATED_AT` overrides; extend `$fillable` with the 9 new columns; extend `casts()` with `last_attempt_at`, `next_retry_at`, `updated_at` as datetime, `is_test` as boolean, `attempt_count` as integer. Add scopes `withStatus`, `forChannel`, `inLast24h`, `retryable` (`status=failed AND attempt_count < 5 AND next_retry_at <= now()`). Add `getIsRetryableAttribute()` accessor.
- [ ] T013 [P] [SHARED] Create `app/Modules/Communication/Domain/Events/NotificationDispatchRetried.php` per data-model.md §7 (readonly properties `dispatch`, `previousAttemptCount`, `adminUserId`; uses `Dispatchable` + `SerializesModels`).
- [ ] T014 [SHARED] Create `app/Modules/Communication/Application/Jobs/DispatchNotificationJob.php` per data-model.md §8. Constructor accepts `int $dispatchId`. `handle()` uses `lockForUpdate()->findOrFail()`, increments `attempt_count`, sets `last_attempt_at = now()`, resolves the channel adapter via `$container->make($dispatch->channel->value . '_adapter')`, calls `$adapter->send($dispatch)`. `$tries = 1`, `$timeout = 30`.

### Adapter updates (each adapter is a separate file — parallelizable)

- [ ] T015 [P] [SHARED] Edit `app/Modules/Communication/Infrastructure/Gateways/FcmPushAdapter.php` to implement the extended contract per contracts/notification-channel-adapter.md §Implementation notes: `name() = "fcm"`; `healthCheck()` resolves the `Messaging` SDK, measures latency, returns `ProviderHealthResult::reachable(...)` or `::unreachable(...)` on `Throwable`; `send()` writes to both new (`provider_name`, `provider_message_id`, `provider_status`, `provider_error_code`, `provider_error_message`) AND legacy (`provider`, `provider_ref`, `error_message`) columns. On failure, compute `next_retry_at = now()->addMinutes(5 * (2 ** ($dispatch->attempt_count - 1)))` if `attempt_count < 5`, else NULL.
- [ ] T016 [P] [SHARED] Edit `app/Modules/Communication/Infrastructure/Gateways/VonageSmsAdapter.php` mirroring T015: `name() = "vonage_sms"`; `healthCheck()` calls `$client->account()->getBalance()`; dual-write provider/error columns; back-off on failure.
- [ ] T017 [P] [SHARED] Edit `app/Modules/Communication/Infrastructure/Gateways/MailchimpEmailAdapter.php` mirroring T015: `name() = "mailchimp_email"`; `healthCheck()` calls `$client->ping->get()`; dual-write provider/error columns; back-off on failure.
- [ ] T018 [P] [SHARED] Edit `app/Modules/Communication/Infrastructure/Gateways/WhatsAppStubAdapter.php` mirroring T015: `name() = "whatsapp_stub"`; `healthCheck()` always returns `ProviderHealthResult::reachable('whatsapp_stub', 0, 'Stub adapter — Phase 1 placeholder')`; `send()` dual-writes provider columns.
- [ ] T019 [P] [SHARED] Create `app/Modules/Communication/Infrastructure/Gateways/NullProviderAdapter.php` per contracts/notification-channel-adapter.md §`NullProviderAdapter`. `send()` throws `NullAdapterInProductionException` if `app()->environment('production')`; otherwise updates `status = Sent`, `provider_name = 'null'`, `provider = 'null'` (legacy), `provider_message_id = 'null-' . Str::ulid()->toBase32()`, `provider_status = 'ok'`, `sent_at = now()`. `healthCheck()` always reachable.

### Service provider wiring

- [ ] T020 [SHARED] Edit `app/Modules/Communication/Providers/CommunicationServiceProvider::register()` per research.md §Decision 5: when `app()->environment(['local', 'testing'])`, bind `NullProviderAdapter` for `push`, `sms`, `whatsapp`, `email` channels. Otherwise bind the real adapters as today. Keep the existing `NotificationDispatcher` binding unchanged.
- [ ] T021 [SHARED] Add `discoverPages` line for `app_path('Modules/Communication/Filament/Pages')` to `app/Providers/Filament/AdminPanelProvider.php` (insert near the existing `discoverPages` calls around lines 77-86), namespaced as `App\\Modules\\Communication\\Filament\\Pages`.

### Foundational tests (run before user-story implementation)

- [ ] T022 [P] [SHARED] Write `tests/Feature/Modules/Communication/ProviderHealth/ContractTest.php` per contracts/notification-channel-adapter.md §Contract test. Use Pest dataset of the four adapter bindings. Assert: `name()` returns non-empty string; `healthCheck()` returns `ProviderHealthResult` with matching `providerName`; `healthCheck()` does not throw; `healthCheck()->note` does not contain any configured provider secret.
- [ ] T023 [P] [SHARED] Write `tests/Feature/Modules/Communication/ProviderHealth/NullProviderAdapterTest.php`: asserts `send()` succeeds locally and sets all new columns correctly; asserts `send()` throws `NullAdapterInProductionException` when `App::detectEnvironment(fn() => 'production')` is forced.

**Checkpoint**: Foundation ready — user story phases can now proceed in parallel (US2/US3 share files, so see notes within each phase).

---

## Phase 3: User Story 1 — View provider health at a glance (Priority: P1) 🎯 MVP

**Goal**: Admin opens `/admin/communication/provider-health` and sees one card per channel with adapter name, live `healthCheck()` result, 24h sent/failed counts, and last-success timestamp. No provider secrets exposed.

**Independent Test**: Local null adapters bound → log in as admin with `view_communication_provider_health` → confirm the four channel cards render, each showing "Reachable" badge, zero counts on fresh data, no API keys visible in rendered HTML.

### Tests for User Story 1 (Pest)

- [ ] T024 [P] [US1] Write `tests/Feature/Modules/Communication/ProviderHealth/CommunicationProviderHealthPageTest.php` with these cases:
   - Admin with permission sees four channel cards (assert presence of "fcm", "vonage_sms", "whatsapp_stub", "mailchimp_email" or the active null-adapter equivalents).
   - Admin without `view_communication_provider_health` receives 403 and the page is hidden from navigation.
   - Channel with `0` dispatches in the last 24h shows the "unknown" status indicator (factory seeds nothing).
   - Channel with more failed than sent dispatches in 24h shows "degraded" badge (factory seeds 3 failed + 1 sent for `provider_name='fcm'`).
   - HTML response body does NOT contain any string from `config('services.fcm.server_key')`, `config('services.vonage.api_secret')`, `config('services.mailchimp.api_key')` (when those config values are non-default).

### Implementation for User Story 1

- [ ] T025 [US1] Create `app/Modules/Communication/Filament/Pages/CommunicationProviderHealthPage.php`. Extends `Filament\Pages\Page`. Static properties: `$navigationGroup = __('admin.nav.groups.communication')`, `$navigationIcon = 'heroicon-o-signal'`, `$navigationLabel = __('communication.provider_health.nav_label')`, `$navigationSort = 50`, `$slug = 'provider-health'`. View template `communication::filament.pages.provider-health`. `static canAccess(): bool` returns `auth()->user()?->can('view_communication_provider_health') ?? false`.
- [ ] T026 [US1] Implement `CommunicationProviderHealthPage::getViewData()` to compute, for each of the four channels, a payload `{ name, healthCheck: ProviderHealthResult, sentLast24h, failedLast24h, lastSuccessAt, statusBadge: 'healthy'|'degraded'|'unknown' }`. Use `NotificationDispatch::query()->forChannel(...)->inLast24h()->...->count()` and adapter `healthCheck()`. Status derivation: `unknown` when sent+failed = 0; `degraded` when `failed > sent`; `healthy` otherwise.
- [ ] T027 [US1] Create Blade view `resources/views/communication/filament/pages/provider-health.blade.php` (or place it inside the module under `app/Modules/Communication/Resources/views/filament/pages/provider-health.blade.php` and register a view namespace in `CommunicationServiceProvider::boot()`). Render four cards using Filament's section + grid components; show `name`, badge per `statusBadge`, latency, sent / failed counts, last success timestamp, and a "Send Test" button that triggers the channel-specific modal action (filled out in US2). No raw config or secret values rendered. Provide bilingual labels via `__('communication.provider_health.*')`.
- [ ] T028 [US1] Run `php artisan shield:generate --all` after T025 lands, then verify the `view_communication_provider_health` Spatie permission is created. Run T024 — all five assertions must pass before proceeding.

**Checkpoint**: US1 fully functional. Admin can see per-channel health at a glance without sending any test message.

---

## Phase 4: User Story 2 — Send a test message via any channel (Priority: P1)

**Goal**: From the health page, admin clicks "Send Test" on a channel card, fills recipient address (+ optional body), and the test is queued as a `NotificationDispatch` with `is_test=true`. Local null adapter completes the test immediately; production adapters call the real API.

**Independent Test**: With `Queue::fake()`, calling `SendTestPushAction::execute(new TestPushDTO('test-token-...', $adminId))` creates a dispatch with `is_test=true`, `channel='push'`, `status='queued'`, and queues a `DispatchNotificationJob`.

### Tests for User Story 2 (Pest)

- [ ] T029 [P] [US2] Write `tests/Feature/Modules/Communication/ProviderHealth/SendTestPushActionTest.php`: happy path creates `NotificationDispatch` with `is_test=true`, `channel='push'`, `attempt_count=0`, context contains `device_token`; `Queue::fake()->assertPushed(DispatchNotificationJob::class)`; invalid device token (too short) throws `ValidationException` from the FormRequest path. Include an audit log assertion (`audit_logs.action='notification.test_sent'`).
- [ ] T030 [P] [US2] Write `tests/Feature/Modules/Communication/ProviderHealth/SendTestSmsActionTest.php`: same shape, with E.164 phone validation. Cover invalid phone (`+0...`, non-numeric) raising `ValidationException`.
- [ ] T031 [P] [US2] Write `tests/Feature/Modules/Communication/ProviderHealth/SendTestWhatsAppActionTest.php`: same shape, with E.164 phone validation.
- [ ] T032 [P] [US2] Write `tests/Feature/Modules/Communication/ProviderHealth/SendTestEmailActionTest.php`: same shape, with RFC 5322 email validation. Cover optional `subjectOverride`.

### Implementation for User Story 2

- [ ] T033 [P] [US2] Create DTO `app/Modules/Communication/Application/DTOs/TestPushDTO.php` per data-model.md §5 (readonly `deviceToken`, `adminUserId`, `bodyOverride`).
- [ ] T034 [P] [US2] Create DTO `app/Modules/Communication/Application/DTOs/TestSmsDTO.php` (`phoneE164`, `adminUserId`, `bodyOverride`).
- [ ] T035 [P] [US2] Create DTO `app/Modules/Communication/Application/DTOs/TestWhatsAppDTO.php` (`phoneE164`, `adminUserId`, `bodyOverride`).
- [ ] T036 [P] [US2] Create DTO `app/Modules/Communication/Application/DTOs/TestEmailDTO.php` (`emailAddress`, `adminUserId`, `subjectOverride`, `bodyOverride`).
- [ ] T037 [P] [US2] Create FormRequest `app/Modules/Communication/Http/Requests/Admin/SendTestPushRequest.php` with rule `device_token => required|string|min:32|max:255`, `body_override => nullable|string|max:500`. Authorize via `view_communication_provider_health` + `send_test_notification`. Method `toDTO(): TestPushDTO`.
- [ ] T038 [P] [US2] Create FormRequest `app/Modules/Communication/Http/Requests/Admin/SendTestSmsRequest.php` with rule `phone_e164 => required|regex:/^\+[1-9]\d{6,14}$/`, `body_override => nullable|string|max:500`. Method `toDTO()`.
- [ ] T039 [P] [US2] Create FormRequest `app/Modules/Communication/Http/Requests/Admin/SendTestWhatsAppRequest.php` mirroring T038.
- [ ] T040 [P] [US2] Create FormRequest `app/Modules/Communication/Http/Requests/Admin/SendTestEmailRequest.php` with `email_address => required|email:rfc,dns`, `subject_override => nullable|string|max:160`, `body_override => nullable|string|max:500`. Method `toDTO()`.
- [ ] T041 [P] [US2] Create Action `app/Modules/Communication/Application/Actions/SendTestPushAction.php`. Single `execute(TestPushDTO $dto): NotificationDispatch` method. Wraps mutations in `DB::transaction(...)`: builds `NotificationDispatch` with `is_test=true`, `channel='push'`, `status='queued'`, `attempt_count=0`, `locale=admin.preferred_locale`, `context=['device_token' => $dto->deviceToken, 'title' => 'InstaParty test push', 'body' => $dto->bodyOverride ?? 'Test from health page']`, `user_id=$dto->adminUserId`. Uses `DB::afterCommit(fn() => DispatchNotificationJob::dispatch($dispatch->id))`. Writes `audit_logs` row with `action='notification.test_sent'`, masked recipient. Returns the created dispatch.
- [ ] T042 [P] [US2] Create Action `app/Modules/Communication/Application/Actions/SendTestSmsAction.php` mirroring T041 but for SMS: context = `['phone_e164', 'sms_body', 'body']`, audit recipient = last 4 digits of phone.
- [ ] T043 [P] [US2] Create Action `app/Modules/Communication/Application/Actions/SendTestWhatsAppAction.php` mirroring T041 but for WhatsApp: context = `['phone_e164', 'body']`, audit recipient = last 4 digits of phone.
- [ ] T044 [P] [US2] Create Action `app/Modules/Communication/Application/Actions/SendTestEmailAction.php` mirroring T041 but for email: context = `['email', 'subject', 'email_body', 'body']`, audit recipient = email with masked local part (e.g., `t***@example.com`).
- [ ] T045 [US2] Wire the four Filament actions on the health page (edit `CommunicationProviderHealthPage` or the Blade view). Each "Send Test" button opens a modal via Filament's slide-over action with the appropriate FormRequest fields. The action's `->action(closure)` resolves the matching `SendTestXxxAction` and dispatches its execute(). On success: `Notification::make()->title(__('communication.provider_health.notifications.test_queued'))->body("public_id: {$dispatch->public_id}")->success()->send()`.
- [ ] T046 [US2] Update `CommunicationServiceProvider::boot()` if any of the four actions or DTOs need explicit binding (most will autowire). Run T029-T032 — all must pass before proceeding.

**Checkpoint**: US2 fully functional. Admin can verify any provider with a real on-demand test from the UI.

---

## Phase 5: User Story 3 — Retry a failed dispatch (Priority: P2)

**Goal**: In the existing `NotificationDispatchResource` list page, admin filters `status=failed` and clicks a row "Retry" action. `RetryFailedDispatchAction` increments `attempt_count`, re-queues, writes audit log, and caps at 5 attempts.

**Independent Test**: Create a `NotificationDispatch` with `status='failed'`, `attempt_count=1`; call `RetryFailedDispatchAction::execute($dispatch, $adminUser)`; assert `attempt_count=2`, `status='queued'`, `last_attempt_at` updated, `DispatchNotificationJob` queued via `Queue::fake()`, audit log entry written.

### Tests for User Story 3 (Pest)

- [ ] T047 [P] [US3] Write `tests/Feature/Modules/Communication/ProviderHealth/RetryFailedDispatchActionTest.php`:
   - Happy path: failed dispatch with `attempt_count=1` → after action: `attempt_count=2`, `status='queued'`, `last_attempt_at` set, `Queue::fake()->assertPushed(DispatchNotificationJob::class, fn($job) => $job->dispatchId === $dispatch->id)`, audit log row exists with `action='notification.retry_queued'`.
   - Non-failed status: dispatch with `status='sent'` → action throws `DispatchNotRetryableException`, no queue push, no audit log mutation.
   - Cap reached: dispatch with `attempt_count=5` → action throws `DispatchNotRetryableException`, audit log entry `action='notification.retry_cap_reached'` is written, no queue push.
   - Concurrent lock: simulate two parallel `execute()` calls on the same dispatch; assert only one succeeds in incrementing `attempt_count`. (Use `DB::transaction` + `lockForUpdate` setup; test via two sequential transactions or via a Pest closure that asserts the lock SQL is issued.)
   - Fires `NotificationDispatchRetried` event via `DB::afterCommit` (use `Event::fake([NotificationDispatchRetried::class])`).
- [ ] T048 [P] [US3] Write `tests/Feature/Modules/Communication/ProviderHealth/NotificationDispatchResourceRetryActionTest.php`: render the Filament resource list table for an admin with `retry_notification_dispatch`; assert the row action "Retry" is visible when `status='failed'` and `attempt_count < 5`; hidden when `status='sent'` or `attempt_count >= 5`; submitting confirms the action and triggers the same flow as T047.

### Implementation for User Story 3

- [ ] T049 [US3] Create Action `app/Modules/Communication/Application/Actions/RetryFailedDispatchAction.php`. Single `execute(NotificationDispatch $dispatch, int $adminUserId): NotificationDispatch` method. Body wraps in `DB::transaction`: re-fetch with `NotificationDispatch::lockForUpdate()->findOrFail($dispatch->id)`; validate `status === Failed` else throw `DispatchNotRetryableException($dispatch, 'not_failed')`; validate `attempt_count < 5` else write `audit_logs` row `action='notification.retry_cap_reached'` and throw `DispatchNotRetryableException($dispatch, 'cap_reached')`; record `$previousAttemptCount`; update `status=Queued`, `next_retry_at=now()`, `last_attempt_at=now()`, clear `provider_error_code`/`provider_error_message`; write `audit_logs` row `action='notification.retry_queued'` with properties `attempt_count`, `provider_name`, `previous_error_code`; `DB::afterCommit(fn() => DispatchNotificationJob::dispatch($dispatch->id))` and `DB::afterCommit(fn() => event(new NotificationDispatchRetried($dispatch, $previousAttemptCount, $adminUserId)))`. Return the updated dispatch.
- [ ] T050 [US3] Edit `app/Modules/Communication/Filament/Resources/NotificationDispatchResource.php`. Add filters: `SelectFilter::make('status')->options(DispatchStatus::class)`, `TernaryFilter::make('is_test')`, `SelectFilter::make('provider_name')->options(/* distinct values from DB */)`. Add row action `Action::make('retry')->label(__('communication.provider_health.retry'))->icon('heroicon-o-arrow-path')->color('warning')->visible(fn($record) => $record->is_retryable && auth()->user()->can('retry_notification_dispatch'))->requiresConfirmation()->action(function ($record) { app(RetryFailedDispatchAction::class)->execute($record, auth()->id()); Notification::make()->title(__('communication.provider_health.notifications.dispatch_retried', ['attempt' => $record->fresh()->attempt_count]))->success()->send(); })`.
- [ ] T051 [US3] Add a "Test" badge column or icon to the `NotificationDispatchResource` table so test dispatches are visually distinguished (use `IconColumn::make('is_test')->boolean()->trueIcon('heroicon-o-beaker')->trueColor('info')` or a `TextColumn` badge — admin spec requires test rows be distinguishable).
- [ ] T052 [US3] Run T047 and T048 — both must pass. Run `php artisan shield:generate --all` to materialize `retry_notification_dispatch` permission.

**Checkpoint**: US3 fully functional. Admin can recover transient provider failures without re-triggering booking events.

---

## Phase 6: User Story 4 — Scheduled automatic retry for transient failures (Priority: P3)

**Goal**: A scheduled artisan command runs every 5 minutes, picks up failed dispatches where `next_retry_at <= now()` and `attempt_count < 5`, and calls `RetryFailedDispatchAction` for each — zero admin intervention.

**Independent Test**: Seed a failed dispatch with `next_retry_at = now()->subMinute()` and `attempt_count=2`; run `php artisan communication:retry-failed-dispatches`; assert the dispatch is re-queued, `attempt_count=3`, audit log entry `action='notification.auto_retry_queued'`.

### Tests for User Story 4 (Pest)

- [ ] T053 [P] [US4] Write `tests/Feature/Modules/Communication/ProviderHealth/RetryFailedDispatchesCommandTest.php`:
   - Seeds 3 due failures + 1 not-yet-due failure + 1 cap-reached failure; runs `Artisan::call('communication:retry-failed-dispatches')`; asserts 3 `DispatchNotificationJob` pushed via `Queue::fake()` (the due ones); the cap-reached row has its `next_retry_at` cleared to NULL; the not-yet-due row is untouched.
   - Command output reports re-queued count.
   - `withoutOverlapping` middleware is registered on the schedule entry (assert via `Schedule::events()` introspection in a separate small test or via `php artisan schedule:list`).

### Implementation for User Story 4

- [ ] T054 [US4] Create `app/Modules/Communication/Console/RetryFailedDispatchesCommand.php`. Signature `communication:retry-failed-dispatches`. `handle()` queries `NotificationDispatch::retryable()->limit(1000)->get()`; for each: try `app(RetryFailedDispatchAction::class)->execute($dispatch, /* adminUserId */ 0)`; catch `DispatchNotRetryableException` for cap-reached rows and write the audit log entry with `action='notification.auto_retry_queued'` distinguishing from the manual flow. Outputs `Re-queued N dispatch(es) for retry.`.
- [ ] T055 [US4] Edit `app/Modules/Communication/Providers/CommunicationServiceProvider::registerSchedule()` (or add a new private method) to register the command: `->command(RetryFailedDispatchesCommand::class)->everyFiveMinutes()->withoutOverlapping()`. Also add the command to the `$this->commands([...])` array in `boot()`.
- [ ] T056 [US4] Refactor T049 (`RetryFailedDispatchAction`) so the audit log action key is parameterised — accept an optional `string $auditAction = 'notification.retry_queued'` argument on `execute()` so the scheduled command can pass `'notification.auto_retry_queued'`. Update T047 tests if the parameter signature changes.
- [ ] T057 [US4] Run T053 — must pass.

**Checkpoint**: US4 fully functional. Transient provider failures recover without admin intervention.

---

## Phase 7: Documentation & Cleanup

**Purpose**: Backfill specs, update memory files, manual QA verification.

- [ ] T058 [P] [DOCS] Append a section `Phase 5.3 — Communication Provider Health` to `docs/specs/09_Phasing_Plan.md` with GOAL, PRD COVERAGE (FR-EXT-025…FR-EXT-035), ADR REFERENCE (ADR-0014 parent), TABLES TOUCHED, DELIVERABLE, EXIT CRITERIA. Resolves the ⚠️ PHASE BACKFILL warning in the spec.
- [ ] T059 [P] [DOCS] Append FR-EXT-025…FR-EXT-035 to `docs/specs/01_PRD.md` (or to a Phase 5 extension subsection). Resolves the ⚠️ BACKFILL NEEDED warning.
- [ ] T060 [P] [DOCS] Append the new `notification_dispatches` columns to `docs/specs/11_DB_Schema.md` §Communication module so the locked schema reflects reality.
- [ ] T061 [P] [DOCS] Append the new audit-log action keys (`notification.test_sent`, `notification.retry_queued`, `notification.retry_cap_reached`, `notification.auto_retry_queued`) to the "Audit-Log Action Catalogue" in `.specify/memory/api-registry.md`.
- [ ] T062 [P] [DOCS] Append a key-component row to `.specify/memory/project-index.md` §"Communication Module" referencing `CommunicationProviderHealthPage`, `NotificationChannelAdapter` (extended), `NullProviderAdapter`, `DispatchNotificationJob`, `RetryFailedDispatchAction`, and `RetryFailedDispatchesCommand`.
- [ ] T063 [DOCS] Run the quickstart.md manual QA flow end-to-end against a fresh local environment. Tick all checkboxes in §7 "Smoke-test summary". Fix any discrepancies and re-run.
- [ ] T064 [DOCS] Run `./vendor/bin/pint app/Modules/Communication/` and `./vendor/bin/phpstan analyse app/Modules/Communication/ --memory-limit=1G` — must be clean.
- [ ] T065 [DOCS] Run the full Pest suite: `./vendor/bin/pest tests/Feature/Modules/Communication/ProviderHealth/ --parallel` — all green. Then `./vendor/bin/pest --bail` over the whole suite to confirm no regression elsewhere.

---

## Dependency Notes

- T002 depends on T001.
- T004 depends on T003.
- T012, T014, T015-T019, T020 all depend on T008 (`ProviderHealthResult`) and T009 (extended contract).
- T020 depends on T019 (`NullProviderAdapter` must exist before binding it).
- T021 has no Phase 2 dependencies — can land in parallel with T008-T020 since it only edits `AdminPanelProvider`.
- T025-T028 (US1) depend on T020 + T021 + T008 + T009 + adapter updates (T015-T019).
- T041-T045 (US2 Actions) depend on the DTOs (T033-T036), FormRequests (T037-T040), and the queued job (T014).
- T049-T051 (US3) depend on T010 (exception), T013 (event), T014 (job), T012 (model scopes/accessor).
- T054 (US4 command) depends on T049 (`RetryFailedDispatchAction`).
- T056 modifies T049 — if both are in flight, do T049 first then T056's refactor.
- T058-T065 (Phase 7) run last after all functional tasks pass tests.

## Parallel-execution suggestions

- **Phase 1 burst**: T001, T003, T005, T006 can run concurrently (different files).
- **Phase 2 contract burst**: T008, T009, T010, T011, T013, T015, T016, T017, T018, T019 are all independent file additions/edits — parallel-safe.
- **Phase 4 DTO/FormRequest burst**: T033-T036 (DTOs) and T037-T040 (FormRequests) and T041-T044 (Actions) are eight independent files; pick the four DTOs first then the four Actions in parallel.
- **Phase 7 documentation burst**: T058-T062 are independent file edits.

## Total task count: 65 (T001 — T065)
