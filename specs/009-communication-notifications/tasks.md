# Tasks: Phase 5.0 — Communication: Notifications

**Input**: Design documents from `specs/009-communication-notifications/`
**Prerequisites**: [spec.md](./spec.md) ✅ | [plan.md](./plan.md) ✅ | [ADR-0010](../../docs/adr/0010-communication-module.md) ✅ Accepted
**Phase**: 5.0 — 2 days, Week 6
**PRD coverage**: FR-23 to FR-26 + PRD §6.1 step 11

**Format** (per user instruction):
```
- [X] T001 — task description
  - File: exact/path/to/file.php
  - Source: PRD FR-XX or Schema §X or ADR-XXXX §X
  - [P] if parallel-safe (must not touch same file as other [P])
```

---

## Phase 1 — Setup & ADR Verification

- [X] T001 — Verify ADR-0010 (Communication Module) exists with `Status: Accepted` and `docs/adr/README.md` index updated
  - File: `docs/adr/0010-communication-module.md`, `docs/adr/README.md`
  - Source: ADR-0010 (created 2026-05-03 in spec phase); Constitution §VI

- [X] T002 — Create the Communication module directory skeleton (8 standard layers + Routes + Database + Resources/lang)
  - File: `app/Modules/Communication/{Domain,Application,Infrastructure,Http,Filament,Routes,Database,Resources/lang/{en,ar},Providers}/.gitkeep`
  - Source: ADR-0010 §5; `.claude/rules/modules.md`

- [X] T003 — Scaffold `CommunicationServiceProvider` with `loadMigrationsFrom`, `loadTranslationsFrom`, route loading hooks, and listener-binding stubs
  - File: `app/Modules/Communication/Providers/CommunicationServiceProvider.php`
  - Source: ADR-0010 §5; `.claude/rules/modules.md` §"Module ServiceProvider"

- [X] T004 — Register `CommunicationServiceProvider` in `bootstrap/providers.php`
  - File: `bootstrap/providers.php`
  - Source: ADR-0010 §12

---

## Phase 2 — Foundational (blocking prerequisites for all user stories)

### Enums (parallel-safe — distinct files)

- [X] T005 — Create `NotificationChannel` PHP backed enum (`push`, `sms`, `whatsapp`, `email`, `in_app`)
  - File: `app/Modules/Communication/Domain/Enums/NotificationChannel.php`
  - Source: Schema §11 `notification_templates.channel` ENUM
  - [P]

- [X] T006 — Create `NotificationAudience` PHP backed enum (`customer`, `vendor`, `admin`)
  - File: `app/Modules/Communication/Domain/Enums/NotificationAudience.php`
  - Source: Schema §11 `notification_templates.audience` ENUM
  - [P]

- [X] T007 — Create `DispatchStatus` PHP backed enum (`queued`, `sent`, `delivered`, `failed`, `bounced`)
  - File: `app/Modules/Communication/Domain/Enums/DispatchStatus.php`
  - Source: Schema §11 `notification_dispatches.status` ENUM
  - [P]

- [X] T008 — Create `EventCategory` PHP backed enum (`booking`, `marketing`, `system`, `chat`, `payment`, `review`)
  - File: `app/Modules/Communication/Domain/Enums/EventCategory.php`
  - Source: Schema §11 `notification_preferences.event_category` ENUM
  - [P]

### Migrations (FK dependency order — sequential)

- [X] T009 — Migration: `notification_templates` table (UNIQUE `(event_key, channel, audience)`, JSON `body` + `subject`, `is_active` default true, utf8mb4)
  - File: `app/Modules/Communication/Database/Migrations/2026_05_03_100001_create_notification_templates_table.php`
  - Source: Schema §11 `notification_templates`; plan.md §3 migration 1

- [X] T010 — Migration: `notification_dispatches` table (append-only — `created_at` only, no `updated_at`; FK to `notification_templates`; polymorphic `reference_type/reference_id`)
  - File: `app/Modules/Communication/Database/Migrations/2026_05_03_100002_create_notification_dispatches_table.php`
  - Source: Schema §11 `notification_dispatches`; Schema §0 append-only list; CLAUDE.md §15; plan.md §3 migration 2

- [X] T011 — Migration: `notification_preferences` table (UNIQUE `(user_id, channel, event_category)`, default `is_enabled = true`, nullable quiet hours)
  - File: `app/Modules/Communication/Database/Migrations/2026_05_03_100003_create_notification_preferences_table.php`
  - Source: Schema §11 `notification_preferences`; plan.md §3 migration 3

- [X] T012 — Run migrations and verify schema in MySQL (`php artisan migrate`)
  - File: (DB only, no source file)
  - Source: plan.md §3

### Models (relationships, casts, scopes ONLY — no business logic)

- [X] T013 — `NotificationTemplate` model — `HasUlids` for `public_id`, `Translatable` trait for `body`+`subject`, `channel`/`audience` enum casts, `variables` array cast, `scopeActive()`, scope by `(eventKey, channel, audience)`
  - File: `app/Modules/Communication/Domain/Models/NotificationTemplate.php`
  - Source: ADR-0010 §6.4; Schema §11; CLAUDE.md §3 (Models hold relationships/casts/scopes only)
  - [P]

- [X] T014 — `NotificationDispatch` model — `HasUlids`, `status` enum cast, `channel`/`locale` enum casts, `context` array cast, FK relationships to `NotificationTemplate` and `User`, polymorphic `reference()`. NO `updated_at`, NO `SoftDeletes`. `$fillable` excludes immutable columns.
  - File: `app/Modules/Communication/Domain/Models/NotificationDispatch.php`
  - Source: ADR-0010 §6.3; Schema §11; CLAUDE.md §15 (append-only)
  - [P]

- [X] T015 — `NotificationPreference` model — `channel`/`event_category` enum casts, `is_enabled` boolean cast, scope `forUser($userId)`, scope `byChannelAndCategory($ch, $cat)`. Static helper `isEnabledFor(User $u, NotificationChannel $ch, EventCategory $cat): bool` returning default-true on missing row.
  - File: `app/Modules/Communication/Domain/Models/NotificationPreference.php`
  - Source: ADR-0010 §6.5; Schema §11; spec.md FR-5.0.06–FR-5.0.08
  - [P]

### Domain Contracts (interfaces consumed/exposed)

- [X] T016 — `NotificationChannelAdapter` contract interface (`send(NotificationDispatch $dispatch): void`)
  - File: `app/Modules/Communication/Domain/Contracts/NotificationChannelAdapter.php`
  - Source: ADR-0010 §6.1
  - [P]

- [X] T017 — `NotificationDispatcher` public contract interface (`dispatch(string $eventKey, User $recipient, array $context, ?string $referenceType = null, ?int $referenceId = null): void`)
  - File: `app/Modules/Communication/Domain/Contracts/NotificationDispatcher.php`
  - Source: ADR-0010 §7 Public Contracts
  - [P]

### Channel Adapters (parallel-safe — distinct files)

- [X] T018 — `FcmPushAdapter` implements `NotificationChannelAdapter` — uses `kreait/laravel-firebase` to send to device tokens fetched from Identity's `UserDeviceRepository` contract; updates dispatch row to `sent` on success or `failed` with error_message on FCM exception
  - File: `app/Modules/Communication/Infrastructure/Gateways/FcmPushAdapter.php`
  - Source: ADR-0010 §5, §6.1; plan.md §9 (kreait/laravel-firebase)
  - [P]

- [X] T019 — `VonageSmsAdapter` implements `NotificationChannelAdapter` — uses `laravel/vonage-notification-channel`; sends to `users.phone_e164`; updates dispatch status accordingly
  - File: `app/Modules/Communication/Infrastructure/Gateways/VonageSmsAdapter.php`
  - Source: ADR-0010 §5, §6.1; plan.md §9 (vonage)
  - [P]

- [X] T020 — `WhatsAppStubAdapter` implements `NotificationChannelAdapter` — does NOT call external API; sets dispatch `status = 'sent'`, `provider = 'whatsapp_stub'`, `sent_at = now()`. Logs payload at debug level only.
  - File: `app/Modules/Communication/Infrastructure/Gateways/WhatsAppStubAdapter.php`
  - Source: ADR-0010 §6.2; spec.md FR-5.0.12; plan.md §11 cut-list
  - [P]

- [X] T021 — `MailchimpEmailAdapter` implements `NotificationChannelAdapter` — uses `mailchimp/marketing` transactional send; subject from template's `subject` JSON in user locale
  - File: `app/Modules/Communication/Infrastructure/Gateways/MailchimpEmailAdapter.php`
  - Source: ADR-0010 §5, §6.1; plan.md §9 (mailchimp)
  - [P]

### Application Services & DTOs

- [X] T022 — `DispatchNotificationDTO` (spatie/laravel-data) — fields: `eventKey`, `channel`, `audience`, `userId`, `context`, `referenceType`, `referenceId`
  - File: `app/Modules/Communication/Application/DTOs/DispatchNotificationDTO.php`
  - Source: plan.md §Implementation Order T014
  - [P]

- [X] T023 — `NotificationPreferenceDTO` (spatie/laravel-data) — fields: `userId`, `channel`, `eventCategory`, `isEnabled`, `quietHoursStart`, `quietHoursEnd`, `timezone`
  - File: `app/Modules/Communication/Application/DTOs/NotificationPreferenceDTO.php`
  - Source: plan.md §Implementation Order
  - [P]

- [X] T024 — `TemplateResolver` service — single SELECT by `(event_key, channel, audience)` UNIQUE key; returns body string in requested locale via `getTranslation()`. Throws `TemplateNotFoundException` if no row or `is_active = false`.
  - File: `app/Modules/Communication/Application/Services/TemplateResolver.php`
  - Source: ADR-0010 §6.4; Schema §11 UNIQUE `(event_key, channel, audience)`

### Infrastructure: Repositories

- [X] T025 — `EloquentNotificationTemplateRepository` implementing internal repo interface — `findByEventChannelAudience()`, `paginateActive()`
  - File: `app/Modules/Communication/Infrastructure/Repositories/EloquentNotificationTemplateRepository.php`
  - Source: `.claude/rules/modules.md` §"Where things go"
  - [P]

- [X] T026 — `EloquentNotificationPreferenceRepository` — `findFor(userId, channel, category)`, `upsert(NotificationPreferenceDTO)`, `listForUser(userId)`
  - File: `app/Modules/Communication/Infrastructure/Repositories/EloquentNotificationPreferenceRepository.php`
  - Source: `.claude/rules/modules.md`
  - [P]

### Core Dispatch Action

- [X] T027 — `DispatchNotificationAction` — single `execute(DispatchNotificationDTO)` method: (1) check `NotificationPreference::isEnabledFor()` (skip silently if disabled, except `system` category which always proceeds); (2) resolve user locale from `users.preferred_locale`; (3) call `TemplateResolver`; on failure write `failed` dispatch row and return; (4) wrap in `DB::transaction`, write dispatch row with `status = 'queued'`; (5) `DB::afterCommit(fn () => $adapter->send($dispatch))` queued via channel adapter from container by `match($channel)`; (6) `DB::afterCommit(fn () => NotificationDispatched::dispatch($dispatch))`
  - File: `app/Modules/Communication/Application/Actions/DispatchNotificationAction.php`
  - Source: ADR-0010 §6.1, §6.4, §6.5; spec.md FR-5.0.04–FR-5.0.09, FR-5.0.15; CLAUDE.md §7 (DB::afterCommit); `.claude/rules/actions.md`

- [X] T028 — Bind `NotificationDispatcher` contract → `DispatchNotificationAction` in `CommunicationServiceProvider::register()`. Bind `NotificationChannelAdapter` resolution by channel enum (container tag).
  - File: `app/Modules/Communication/Providers/CommunicationServiceProvider.php`
  - Source: ADR-0010 §7

### Domain Event

- [X] T029 — `NotificationDispatched` domain event class with `dispatchId`, `userId`, `channel`, `eventKey`, `status` payload
  - File: `app/Modules/Communication/Domain/Events/NotificationDispatched.php`
  - Source: ADR-0010 §7 Events we publish; plan.md §7
  - [P]

### Template Seeder (EN + AR for 8 event keys)

- [X] T030 — `NotificationTemplateSeeder` — seed 4 core event keys (`booking.submitted`, `booking.modified`, `booking.confirmed`, `payment.captured`) × audience (customer, vendor) × channels with EN + AR bodies and subjects (where applicable). Idempotent (use `updateOrCreate` by UNIQUE key).
  - File: `app/Modules/Communication/Database/Seeders/NotificationTemplateSeeder.php`
  - Source: spec.md FR-5.0.13; plan.md §Implementation Order T015

- [X] T031 — Extend `NotificationTemplateSeeder` with 4 per-type event keys (`rental.delivery_scheduled`, `sale.preparation_started`, `digital.delivered`, `digital.expiring_soon`) × audience customer × channels per FR-5.0.03
  - File: `app/Modules/Communication/Database/Seeders/NotificationTemplateSeeder.php`
  - Source: spec.md FR-5.0.03; ADR-0010 §4

- [X] T032 — Register seeder in `DatabaseSeeder` and run via `php artisan db:seed --class=NotificationTemplateSeeder`
  - File: `database/seeders/DatabaseSeeder.php`
  - Source: plan.md §Implementation Order

---

## Phase 3 — User Story 1 (P1): Customer receives booking lifecycle notifications

**Story Goal:** Customer receives push + email on booking lifecycle events in their preferred locale.
**Independent Test:** Fire `PaymentCaptured` event → assert two `notification_dispatches` rows (push + email) with `locale = users.preferred_locale`.

- [X] T033 — `OnBookingSubmitted` queued listener (`implements ShouldQueue`) — listens to `Booking\Events\BookingSubmitted`; calls `NotificationDispatcher` for customer (push + email) AND for each vendor (push + sms). Reference event payload only; no Booking model import.
  - File: `app/Modules/Communication/Application/Listeners/OnBookingSubmitted.php`
  - Source: spec.md FR-5.0.01, FR-5.0.02, US1 scenario 3; plan.md §7

- [X] T034 — `OnBookingModified` queued listener — push + email to customer per template `booking.modified`
  - File: `app/Modules/Communication/Application/Listeners/OnBookingModified.php`
  - Source: spec.md FR-5.0.01, US1 scenario 3
  - [P]

- [X] T035 — `OnBookingConfirmed` queued listener — push + email + sms to customer; push to vendor
  - File: `app/Modules/Communication/Application/Listeners/OnBookingConfirmed.php`
  - Source: spec.md FR-5.0.01, FR-5.0.02
  - [P]

- [X] T036 — `OnPaymentCaptured` queued listener — push + email to customer; push to vendor (per US1 scenario 1)
  - File: `app/Modules/Communication/Application/Listeners/OnPaymentCaptured.php`
  - Source: spec.md FR-5.0.01, US1 scenario 1
  - [P]

- [X] T037 — Register T033–T036 listeners in `CommunicationServiceProvider::boot()` event map
  - File: `app/Modules/Communication/Providers/CommunicationServiceProvider.php`
  - Source: ADR-0010 §7 Events we consume

### Pest tests for US1

- [X] T038 — Pest happy-path: `PaymentCaptured` fires → 2 dispatch rows (push + email), `locale` matches `users.preferred_locale`, `template_id` resolved correctly. Test for `preferred_locale = 'ar'` AND `'en'`.
  - File: `tests/Feature/Modules/Communication/CustomerBookingNotificationsTest.php`
  - Source: spec.md US1 scenarios 1, 2

- [X] T039 — Pest: missing template scenario — when no template exists for `(booking.modified, push, customer)`, `DispatchNotificationAction` writes `status = 'failed'` row with `error_message`, does NOT throw
  - File: `tests/Feature/Modules/Communication/CustomerBookingNotificationsTest.php`
  - Source: spec.md US1 scenario 4; FR-5.0.09

- [X] T040 — Pest: `BookingModified` → push dispatch contains booking `public_id` in `context` JSON
  - File: `tests/Feature/Modules/Communication/CustomerBookingNotificationsTest.php`
  - Source: spec.md US1 scenario 3

---

## Phase 4 — User Story 2 (P1): Vendor + per-type fulfillment notifications

**Story Goal:** Vendor notified within SLA; per-type fulfillment events trigger correct notifications.
**Independent Test:** Fire `RentalDeliveryScheduled` → push + SMS dispatched to customer using `rental.delivery_scheduled` template.

- [X] T041 — `OnRentalDeliveryScheduled` queued listener — push + sms to customer using event_key `rental.delivery_scheduled`
  - File: `app/Modules/Communication/Application/Listeners/OnRentalDeliveryScheduled.php`
  - Source: spec.md FR-5.0.03, US2 scenario 2
  - [P]

- [X] T042 — `OnSalePreparationStarted` queued listener — push to customer using event_key `sale.preparation_started`
  - File: `app/Modules/Communication/Application/Listeners/OnSalePreparationStarted.php`
  - Source: spec.md FR-5.0.03
  - [P]

- [X] T043 — `OnDigitalDelivered` queued listener — push + email to customer using event_key `digital.delivered`
  - File: `app/Modules/Communication/Application/Listeners/OnDigitalDelivered.php`
  - Source: spec.md FR-5.0.03, US2 scenario 3
  - [P]

- [X] T044 — `OnDigitalExpiringSoon` queued listener — push + email to customer using event_key `digital.expiring_soon`; include expiry date in `context`
  - File: `app/Modules/Communication/Application/Listeners/OnDigitalExpiringSoon.php`
  - Source: spec.md FR-5.0.03, US2 scenario 4
  - [P]

- [X] T045 — Register T041–T044 listeners in `CommunicationServiceProvider::boot()` event map
  - File: `app/Modules/Communication/Providers/CommunicationServiceProvider.php`
  - Source: ADR-0010 §7

### Pest tests for US2 (per-type coverage required)

- [X] T046 — Pest: vendor receives push + SMS on `BookingSubmitted` (per US2 scenario 1)
  - File: `tests/Feature/Modules/Communication/VendorBookingNotificationsTest.php`
  - Source: spec.md US2 scenario 1

- [X] T047 — Pest: rental — `RentalDeliveryScheduled` → push + sms dispatch with template `rental.delivery_scheduled`. Pest group `rental`.
  - File: `tests/Feature/Modules/Communication/PerTypeFulfillmentNotificationsTest.php`
  - Source: spec.md US2 scenario 2; ADR-0010 §4 (per-type)

- [X] T048 — Pest: sale — `SalePreparationStarted` → push dispatch with template `sale.preparation_started`. Pest group `sale`.
  - File: `tests/Feature/Modules/Communication/PerTypeFulfillmentNotificationsTest.php`
  - Source: spec.md FR-5.0.03; ADR-0010 §4

- [X] T049 — Pest: digital — `DigitalDelivered` → push + email dispatch with template `digital.delivered`. Pest group `digital`.
  - File: `tests/Feature/Modules/Communication/PerTypeFulfillmentNotificationsTest.php`
  - Source: spec.md US2 scenario 3; ADR-0010 §4

- [X] T050 — Pest: digital — `DigitalExpiringSoon` → push + email dispatch with template `digital.expiring_soon`; expiry date present in `context`. Pest group `digital`.
  - File: `tests/Feature/Modules/Communication/PerTypeFulfillmentNotificationsTest.php`
  - Source: spec.md US2 scenario 4

---

## Phase 5 — User Story 3 (P2): Customer manages notification preferences (API)

**Story Goal:** Customer + vendor can read and update notification preferences via REST API; system category is protected.
**Independent Test:** Disable `push/marketing` → fire marketing notification → assert no push dispatch row created.

- [X] T051 — `UpdateNotificationPreferenceAction` — single `execute(NotificationPreferenceDTO): NotificationPreference` method. Reject with `SystemCategoryCannotBeDisabledException` if `event_category = 'system'` AND `is_enabled = false`. UPSERT via repository.
  - File: `app/Modules/Communication/Application/Actions/UpdateNotificationPreferenceAction.php`
  - Source: spec.md FR-5.0.06, FR-5.0.07; US3 scenario 3; `.claude/rules/actions.md`

- [X] T052 — `UpdateNotificationPreferenceRequest` Form Request with `@bodyParam` PHPDoc on every field (Scribe-compatible)
  - File: `app/Modules/Communication/Http/Requests/UpdateNotificationPreferenceRequest.php`
  - Source: plan.md §8 (@bodyParam); spec.md API Endpoints PUT body
  - Body fields:
    - `is_enabled` boolean required — `@bodyParam is_enabled boolean required Whether to enable this channel for this event category. Example: true`
    - `quiet_hours_start` string nullable HH:MM regex — `@bodyParam quiet_hours_start string optional Quiet hours start time (HH:MM, user's timezone). Example: "22:00"`
    - `quiet_hours_end` string nullable HH:MM regex `required_with:quiet_hours_start` — `@bodyParam quiet_hours_end string optional Quiet hours end time (HH:MM). Example: "08:00"`
  - Custom validation rule: reject `is_enabled=false` when `event_category=system` (route param) → returns 422 with code `system_notifications_cannot_be_disabled`

- [X] T053 — `NotificationPreferenceResource` API Resource — returns `channel`, `event_category`, `is_enabled`, `quiet_hours_start`, `quiet_hours_end`. Includes `@response` PHPDoc with EN + AR example bodies (preferences have no translatable text but the example shows both Accept-Language headers produce same shape).
  - File: `app/Modules/Communication/Http/Resources/NotificationPreferenceResource.php`
  - Source: plan.md §8 (@response); CLAUDE.md §12 (ApiResponse envelope)

- [X] T054 — `NotificationPreferenceController` — 4 thin methods (max 3-line bodies): `indexCustomer()`, `updateCustomer(UpdateNotificationPreferenceRequest, $channel, $eventCategory)`, `indexVendor()`, `updateVendor(...)`. Each delegates to repository read or `UpdateNotificationPreferenceAction::execute()`.
  - File: `app/Modules/Communication/Http/Controllers/NotificationPreferenceController.php`
  - Source: CLAUDE.md §1 (Thin controllers); plan.md §8 endpoints

- [X] T055 — Customer routes — register `GET` + `PUT` `/notification-preferences` under `auth:sanctum` + role:customer middleware
  - File: `app/Modules/Communication/Routes/customer.php`
  - Source: spec.md API Endpoints
  - [P]

- [X] T056 — Vendor routes — register `GET` + `PUT` `/notification-preferences` under `auth:sanctum` + role:vendor middleware
  - File: `app/Modules/Communication/Routes/vendor.php`
  - Source: spec.md API Endpoints
  - [P]

- [X] T057 — Wire `customer.php` + `vendor.php` route loading in `CommunicationServiceProvider::boot()` with `/api/v1/customer` and `/api/v1/vendor` prefixes
  - File: `app/Modules/Communication/Providers/CommunicationServiceProvider.php`
  - Source: `.claude/rules/modules.md` §"Module ServiceProvider"

### API Documentation tasks (mandatory per Phase 1 PRD)

- [X] T058 — Update `.specify/memory/api-registry.md` — promote 4 Phase 5.0 endpoints from `📝 partial` to `✅ postman` after Bruno + Postman files exist
  - File: `.specify/memory/api-registry.md`
  - Source: plan.md §8 api-registry plan

- [X] T059 — Create Bruno collection for Communication endpoints (4 requests: GET + PUT customer, GET + PUT vendor)
  - File: `docs/api/collections/communication.bru`
  - Source: plan.md §8 Bruno collection
  - [P]

- [X] T060 — Create Postman collection for Communication endpoints (4 requests)
  - File: `docs/api/collections/communication.postman_collection.json`
  - Source: plan.md §8 Postman collection
  - [P]

### Pest tests for US3

- [X] T061 — Pest happy path: customer with `push/marketing` opt-out disabled → fire marketing event → no push dispatch row created (per US3 scenario 1)
  - File: `tests/Feature/Modules/Communication/NotificationPreferenceTest.php`
  - Source: spec.md US3 scenario 1; FR-5.0.06

- [X] T062 — Pest: `marketing` opt-out does NOT block `booking` category dispatches (per US3 scenario 2)
  - File: `tests/Feature/Modules/Communication/NotificationPreferenceTest.php`
  - Source: spec.md US3 scenario 2

- [X] T063 — Pest: API returns 422 with `system_notifications_cannot_be_disabled` when attempting to disable `system` category (per US3 scenario 3)
  - File: `tests/Feature/Modules/Communication/NotificationPreferenceTest.php`
  - Source: spec.md US3 scenario 3; FR-5.0.07

- [X] T064 — Pest: missing preference row → default `is_enabled = true`, dispatch proceeds (per US3 scenario 4)
  - File: `tests/Feature/Modules/Communication/NotificationPreferenceTest.php`
  - Source: spec.md US3 scenario 4; FR-5.0.08

- [X] T065 — Pest auth: `GET` and `PUT` preference endpoints return 401 when unauthenticated
  - File: `tests/Feature/Modules/Communication/NotificationPreferenceApiTest.php`
  - Source: Constitution §VII (auth coverage); CLAUDE.md Testing §"Required coverage"

- [X] T066 — Pest authz: vendor calling `/api/v1/customer/notification-preferences` returns 403; customer calling `/api/v1/vendor/notification-preferences` returns 403
  - File: `tests/Feature/Modules/Communication/NotificationPreferenceApiTest.php`
  - Source: CLAUDE.md Testing §"Required coverage" (authorization)

- [X] T067 — Pest validation: `PUT` body without `is_enabled` returns 422; invalid HH:MM format returns 422; `quiet_hours_end` missing when `quiet_hours_start` provided returns 422
  - File: `tests/Feature/Modules/Communication/NotificationPreferenceApiTest.php`
  - Source: T052 validation rules

- [X] T068 — Pest locale: `Accept-Language: en` and `Accept-Language: ar` both return same `NotificationPreferenceResource` shape (preferences have no translatable text but envelope must respect locale)
  - File: `tests/Feature/Modules/Communication/NotificationPreferenceApiTest.php`
  - Source: Constitution §IV (bilingual coverage on every user-facing endpoint); plan.md §5

---

## Phase 6 — User Story 4 (P2): Admin manages notification templates (Filament)

**Story Goal:** Admin can CRUD notification templates from Filament with EN + AR tabs.
**Independent Test:** Admin edits AR body → fires event → dispatch uses new body.

- [X] T069 — `NotificationTemplateResource` Filament Resource — uses `Filament\Resources\Concerns\Translatable` trait + `filament/spatie-laravel-translatable-plugin`. Form: `event_key` TextInput, `channel` Select (enum), `audience` Select (enum), `body` Textarea (translatable EN/AR tabs both required), `subject` Textarea (translatable, nullable), `variables` KeyValue, `is_active` Toggle. Table: `event_key`, `channel` badge, `audience` badge, `is_active` toggle, last updated. Filters: `channel`, `audience`, `is_active`. Navigation group "Communications".
  - File: `app/Modules/Communication/Filament/Resources/NotificationTemplateResource.php`
  - Source: ADR-0010 §8; spec.md US4; CLAUDE.md §"When Generating Filament Resources"; `.claude/rules/filament-components.md`

- [X] T070 — `NotificationTemplateResource\Pages\{ListNotificationTemplates,CreateNotificationTemplate,EditNotificationTemplate}` page classes (auto-scaffolded shape)
  - File: `app/Modules/Communication/Filament/Resources/NotificationTemplateResource/Pages/`
  - Source: T069 dependency

- [X] T071 — Run `php artisan shield:generate --all` to register permissions for `NotificationTemplateResource`
  - File: (CLI side effect; updates `permissions` table + Shield policies)
  - Source: ADR-0010 §8; CLAUDE.md §"When Generating Filament Resources"

### Pest tests for US4

- [X] T072 — Pest: admin updates AR body via Filament action → next dispatch for AR-locale customer uses new body (per US4 scenario 1)
  - File: `tests/Feature/Modules/Communication/AdminTemplateManagementTest.php`
  - Source: spec.md US4 scenario 1

- [X] T073 — Pest: admin sets `is_active = false` on template → dispatch attempts for that template log `status = 'failed'` (per US4 scenario 2; treated as missing template per ADR-0010 §6.4)
  - File: `tests/Feature/Modules/Communication/AdminTemplateManagementTest.php`
  - Source: spec.md US4 scenario 2

- [X] T074 — Pest: Filament form rejects save when AR body is empty (per US4 scenario 3 — locale validation)
  - File: `tests/Feature/Modules/Communication/AdminTemplateManagementTest.php`
  - Source: spec.md US4 scenario 3; Constitution §IV (both locales required)

---

## Phase 7 — User Story 5 (P3): Per-type template resolution correctness

**Story Goal:** Each per-type event key resolves to its dedicated template — no fallback to a different type's template.
**Independent Test:** Seed `rental.delivery_scheduled` only; fire `SalePreparationStarted` → assert NO dispatch (no fallback) and `failed` row written.

- [X] T075 — Pest: when only `rental.delivery_scheduled` template exists, `SalePreparationStarted` event produces a `failed` dispatch row (no fallback to rental). Validates ADR-0010 §6.4 no-fallback rule.
  - File: `tests/Feature/Modules/Communication/PerTypeTemplateResolutionTest.php`
  - Source: spec.md US5 scenario 2; ADR-0010 §6.4

- [X] T076 — Pest: all 4 per-type event keys (rental + sale + 2 digital) resolve to distinct `template_id`s. Pest groups: `rental`, `sale`, `digital`.
  - File: `tests/Feature/Modules/Communication/PerTypeTemplateResolutionTest.php`
  - Source: spec.md US5 scenario 1; FR-5.0.03

---

## Phase 8 — Polish & Cross-cutting Concerns

### Architecture tests

- [X] T077 — Architecture test: Communication module does NOT import Eloquent models from other modules (Booking/Payments/Identity/Settlement/Catalog)
  - File: `tests/Architecture/NoCrossModuleModelImportsTest.php`
  - Source: plan.md §10; Constitution §I; `.claude/rules/modules.md`

- [X] T078 — Architecture test: `NotificationDispatch` does NOT use `SoftDeletes` trait and has no `deleted_at` column
  - File: `tests/Architecture/AppendOnlyTablesHaveNoSoftDeletesTest.php`
  - Source: plan.md §10; Constitution §V; CLAUDE.md §15

- [X] T079 — Architecture test: Communication module contains no `if/elseif` on product type strings (per CLAUDE.md §8)
  - File: `tests/Architecture/NoIfElseOnProductTypeStringTest.php`
  - Source: plan.md §10; Constitution §II

- [X] T080 — Architecture test: `DispatchNotificationAction` invokes `DB::afterCommit` before dispatching `NotificationDispatched` event (no synchronous `event()` call inside transaction)
  - File: `tests/Architecture/EventAfterCommitTest.php`
  - Source: plan.md §10; Constitution §IX; CLAUDE.md §7

### Translation files

- [X] T081 — Add EN translation file for Communication module (validation messages, error labels, Filament resource labels)
  - File: `app/Modules/Communication/Resources/lang/en/communication.php`
  - Source: `.claude/rules/modules.md` §"Module ServiceProvider" (loadTranslationsFrom)
  - [P]

- [X] T082 — Add AR translation file for Communication module
  - File: `app/Modules/Communication/Resources/lang/ar/communication.php`
  - Source: Constitution §IV (EN + AR mandatory)
  - [P]

### Unit tests

- [X] T083 — Pest unit test: `TemplateResolver::resolve()` returns body in requested locale, throws `TemplateNotFoundException` on missing or `is_active = false`
  - File: `tests/Unit/Modules/Communication/TemplateResolverTest.php`
  - Source: ADR-0010 §6.4; T024
  - [P]

- [X] T084 — Pest unit test: `NotificationPreference::isEnabledFor()` static helper returns `true` on missing row, respects `is_enabled`, always returns `true` for `system` category regardless of row state
  - File: `tests/Unit/Modules/Communication/NotificationPreferenceTest.php`
  - Source: T015; ADR-0010 §6.5; FR-5.0.07, FR-5.0.08
  - [P]

- [X] T085 — Pest unit test: `WhatsAppStubAdapter::send()` writes `provider = 'whatsapp_stub'`, sets `status = 'sent'`, never invokes Guzzle/HTTP client
  - File: `tests/Unit/Modules/Communication/WhatsAppStubAdapterTest.php`
  - Source: ADR-0010 §6.2; FR-5.0.12
  - [P]

### Final verification

- [X] T086 — Run `./vendor/bin/pint` on all new files
  - File: (CLI)
  - Source: CLAUDE.md §Build & Run Commands

- [X] T087 — Run `./vendor/bin/phpstan analyse` and resolve any errors in `app/Modules/Communication/`
  - File: (CLI)
  - Source: CLAUDE.md §Build & Run Commands

- [X] T088 — Run `./vendor/bin/pest --group=communication` — all green; then `./vendor/bin/pest --group=rental --group=sale --group=digital` for per-type coverage
  - File: (CLI)
  - Source: spec.md Exit Criteria SC-001–SC-005; CLAUDE.md Testing patterns

- [X] T089 — Verify Phase 5.0 Exit Criteria (spec.md §Success Criteria SC-001 through SC-005 all checkboxes ticked)
  - File: `specs/009-communication-notifications/spec.md`
  - Source: spec.md §Success Criteria

---

## Dependencies & Execution Order

```
Phase 1 (T001–T004): Setup — sequential, blocks everything
   ↓
Phase 2 (T005–T032): Foundational — must complete before any user story
   • T005–T008 (enums) parallel
   • T009 → T010 → T011 → T012 (migrations sequential, FK order)
   • T013–T015 (models) parallel after migrations
   • T016–T017 (contracts) parallel
   • T018–T021 (channel adapters) parallel
   • T022–T023 (DTOs) parallel
   • T024 (TemplateResolver) after T013
   • T025–T026 (repositories) parallel after models
   • T027 (DispatchNotificationAction) after T024 + T026 + T015
   • T028 (DI bindings) after T027 + adapters
   • T029 (event class) parallel
   • T030 → T031 → T032 (seeders) sequential
   ↓
Phase 3 (T033–T040): US1 customer notifications
Phase 4 (T041–T050): US2 vendor + per-type fulfillment (independent of US1)
Phase 5 (T051–T068): US3 preferences API (independent of US1, US2)
Phase 6 (T069–T074): US4 Filament admin (independent of US1, US2, US3)
Phase 7 (T075–T076): US5 per-type resolution validation (depends on T031 seeder)
   ↓
Phase 8 (T077–T089): Polish — architecture tests, translations, unit tests, CLI verification
```

**MVP scope (US1 only):** T001–T040 = customer receives push + email on booking lifecycle events. This alone delivers PRD §6.1 step 11.

**Parallel execution opportunities:**
- Phase 2 enums (T005–T008): 4 files, 0 dependencies → run in parallel
- Phase 2 channel adapters (T018–T021): 4 distinct files → run in parallel after T016
- Phase 3 listeners (T034–T036): 3 distinct listener files → parallel
- Phase 4 listeners (T041–T044): 4 distinct listener files → parallel
- Phase 5 routes (T055–T056) and API doc (T059–T060): parallel
- Phase 8 translations (T081–T082) and unit tests (T083–T085): all parallel

---

## Self-Check Results

✅ **Every task traces to FR/Schema/ADR/spec source** — every task line includes a `Source:` line citing PRD FR-X, Schema §11, ADR-0010 §X, or spec.md scenario.

✅ **No Phase 2 features** — campaigns (`campaigns`, `campaign_runs`, `campaign_recipients`), Mailchimp list sync, scheduled notifications, Reverb in-app delivery, and WhatsApp real templates all explicitly deferred per plan.md §11 cut-list. Tasks build only the dispatch core that those Phase 1.5/5.3 features will consume.

✅ **Every API endpoint has documentation tasks**:
- `GET/PUT /api/v1/customer/notification-preferences` and `GET/PUT /api/v1/vendor/notification-preferences` (4 endpoints) — all have:
  - Form Request task with `@bodyParam` PHPDoc (T052)
  - Resource task with `@response` PHPDoc (T053)
  - api-registry.md update task (T058)
  - Bruno collection task (T059)
  - Postman collection task (T060)

**Total: 89 tasks across 8 phases.**
