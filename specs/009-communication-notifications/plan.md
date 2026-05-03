# Implementation Plan: Communication — Notifications (Phase 5.0)

**Branch**: `009-communication-notifications` | **Date**: 2026-05-03 | **Spec**: [spec.md](./spec.md)
**ADR**: [ADR-0010 — Communication Module](../../docs/adr/0010-communication-module.md) — **Accepted 2026-05-03**

---

## Summary

Build the Communication module's notification infrastructure — a new `app/Modules/Communication/` that listens to Booking and Payments domain events, resolves notification templates by `(event_key × channel × audience × locale)`, respects per-user `notification_preferences`, and dispatches through four channel adapters (FCM push, Vonage SMS, WhatsApp stub, Mailchimp email).

Three tables, four channel adapters, eight event listeners, one `DispatchNotificationAction`, one Filament resource for template management, and two API endpoints for user preference management. Template seeder covers four core event keys (booking.submitted, booking.modified, booking.confirmed, payment.captured) plus four per-type event keys (rental.delivery_scheduled, sale.preparation_started, digital.delivered, digital.expiring_soon) with EN + AR bodies.

Depends on: Booking (Phase 3.x — `BookingSubmitted`, `BookingModified`, `BookingConfirmed`, fulfillment events), Payments (Phase 4.0 — `PaymentCaptured`), Identity (Phase 1.0 — `user_devices` device tokens via contract).

---

## Technical Context

**Language/Version**: PHP 8.3, Laravel 12
**Primary Dependencies**:
- `kreait/laravel-firebase` — FCM push adapter (device token → Firebase Cloud Messaging)
- `laravel/vonage-notification-channel` — Vonage SMS adapter
- `netflie/whatsapp-cloud-api` — WhatsApp Cloud API (stub in Phase 1 — no real API calls)
- `mailchimp/marketing` — Mailchimp transactional email adapter
- `spatie/laravel-translatable` — `notification_templates.body` and `.subject` JSON columns
- `spatie/laravel-permission` — communication-specific permissions
- `bezhansalleh/filament-shield` — auto-generate Filament resource permissions
- `filament/spatie-laravel-translatable-plugin` — EN/AR tabs in `NotificationTemplateResource`

**Storage**: MySQL 8 — 3 new tables (`notification_templates`, `notification_dispatches`, `notification_preferences`). `notification_dispatches` is append-only (no `updated_at`; status-only mutations allowed per `11_DB_Schema.md` §0 append-only list).

**Testing**: Pest — feature tests for preference API endpoints; integration tests for event-to-dispatch flow; unit tests for `TemplateResolver`; architecture tests for no-cross-module-import, event-after-commit, no-if-elseif-on-type-strings.

**Target Platform**: Linux (Docker Compose dev, Hetzner CCX13 staging)
**Project Type**: Modular monolith API + Filament admin

**Performance Goals**:
- Notification dispatch job completes within 3 seconds of event fire (queued)
- Template resolution p95 under 20ms (single indexed SELECT by UNIQUE key)
- Preference check p95 under 5ms (UNIQUE index on `user_id, channel, event_category`)

**Constraints**:
- All event listeners are queued (`implements ShouldQueue`) — dispatch is non-blocking
- `notification_dispatches` append-only: only `status`, `sent_at`, `delivered_at`, `error_message`, `provider_ref` may be updated after row creation
- Domain events fire after `DB::afterCommit()` only (enforced by Booking and Payments modules already)
- WhatsApp adapter is a stub: records dispatch row with `provider = 'whatsapp_stub'`, never calls external API
- Both `en` and `ar` template bodies are required — validation blocks saving with either missing
- `system` event category in `notification_preferences` can never be set to `is_enabled = false`
- No idempotency key on preference update (idempotent by nature — UPSERT)

---

## 1. ADR Reference

**ADR-0010 — Communication Module** — Accepted 2026-05-03
Path: `docs/adr/0010-communication-module.md`

Key decisions from ADR-0010 that drive implementation:
- **§6.1** — `NotificationChannelAdapter` contract: all 4 adapters implement `send(NotificationDispatch $dispatch): void`. Resolved from container by channel enum.
- **§6.2** — WhatsApp stub: `WhatsAppStubAdapter` writes dispatch row with `provider = 'whatsapp_stub'`, never calls external API. Phase 1.5 replaces with real adapter.
- **§6.3** — `notification_dispatches` append-only: only status columns mutable post-creation.
- **§6.4** — Template resolution: single `(event_key, channel, audience)` lookup, no fallback chain, both locales required.
- **§6.5** — Preference check before dispatch: absent row = enabled; `system` category always dispatches.

---

## 2. Constitution Check

| Rule | Status | Notes |
|---|---|---|
| Modular monolith — `app/Modules/Communication/` | ✅ PASS | New module with full 8-layer layout per `CLAUDE.md` §Module Layout |
| Thin controllers (3-line action body max) | ✅ PASS | `NotificationPreferenceController` → `UpdateNotificationPreferenceAction::execute()` |
| Per-purpose Actions, single `execute()` | ✅ PASS | `DispatchNotificationAction` (one method), `UpdateNotificationPreferenceAction` (one method) |
| Models hold relationships/casts/scopes only | ✅ PASS | All 3 models lean; no business logic; `NotificationDispatch` has `status` enum cast, `context` array cast |
| `match($enum)` not if/elseif on type strings | ✅ PASS | `DispatchNotificationAction` uses `match($channel)` to resolve adapter from container; no if/elseif on type strings |
| No cross-module Eloquent Model imports | ✅ PASS | Device tokens fetched via `Identity\Domain\Contracts\UserDeviceRepository` contract; booking/payment data via event payloads only |
| Money discipline | ✅ N/A | No money columns in Communication module |
| ULID `public_id` on top-level entities | ✅ PASS | `notification_templates.public_id`, `notification_dispatches.public_id` — CHAR(26) UNIQUE |
| Translatable JSON columns | ✅ PASS | `notification_templates.body` and `.subject` are JSON columns with `spatie/laravel-translatable` |
| `notification_dispatches` append-only | ✅ PASS | `created_at` only (no `updated_at`); only `status`, `sent_at`, `delivered_at`, `error_message`, `provider_ref` mutable |
| Soft-deletes only on whitelisted tables | ✅ PASS | None of the 3 Communication tables have `softDeletes()` |
| Domain events fire after `DB::afterCommit()` | ✅ PASS | Communication listeners are triggered by events already fired via `DB::afterCommit()` in Booking/Payments. `DispatchNotificationAction` fires `NotificationDispatched` via `DB::afterCommit()` after writing the dispatch row. |
| Idempotency on state-changing endpoints | ✅ N/A | Notification preference update (`PUT`) is an upsert — inherently idempotent. No `idempotency_keys` table entry needed per spec §Requirements FR-5.0.08 and the constitution's idempotency scope (payment-mutating endpoints only). |
| `ApiResponse` envelope on every API response | ✅ PASS | Both preference endpoints return `ApiResponse{data, meta, errors}` |
| Locale conversion at API Resource layer | ✅ PASS | `NotificationPreferenceResource` — no translatable fields in preferences; dispatch locale stored explicitly. Template body converted in `TemplateResolver` at dispatch time using `users.preferred_locale`, never in business logic. |
| `filament-shield` permissions per resource | ✅ PASS | `NotificationTemplateResource` permissions generated by `php artisan shield:generate --all` |
| No new packages outside `10_Package_List.md` | ✅ PASS | All 4 channel packages already in `10_Package_List.md` §2 Notifications section |
| No Phase 2 features | ✅ PASS | Campaigns (`campaigns`, `campaign_runs`, `campaign_recipients`) deferred to Phase 5.3; WhatsApp real templates deferred to Phase 1.5 |
| EN + AR required for all translatable fields | ✅ PASS | Template form validation requires both locales; seeder populates both |

**GATE RESULT: ALL PASS — proceed to implementation.**

---

## 3. Schema

Matches `docs/specs/11_DB_Schema.md` §11 (Communication module). Migrations run in FK dependency order:

| # | Migration filename | Tables / Changes |
|---|---|---|
| 1 | `2026_05_03_100001_create_notification_templates_table.php` | `notification_templates` — UNIQUE `(event_key, channel, audience)`; `body` + `subject` JSON |
| 2 | `2026_05_03_100002_create_notification_dispatches_table.php` | `notification_dispatches` — append-only; FK → `notification_templates.id`; `created_at` only (no `updated_at`) |
| 3 | `2026_05_03_100003_create_notification_preferences_table.php` | `notification_preferences` — UNIQUE `(user_id, channel, event_category)` |

### `notification_templates` (migration 1)

```
id                  BIGINT UNSIGNED PK AUTO_INCREMENT
public_id           CHAR(26) UNIQUE NOT NULL            ULID
event_key           VARCHAR(120) NOT NULL               e.g. 'booking.submitted', 'rental.delivery_scheduled'
channel             ENUM('push','sms','whatsapp','email','in_app') NOT NULL
audience            ENUM('customer','vendor','admin') NOT NULL
subject             JSON NULL                            {"en": "...", "ar": "..."}
body                JSON NOT NULL                       {"en": "...", "ar": "..."}
variables           JSON NULL                           documented placeholder tokens
is_active           BOOLEAN NOT NULL DEFAULT true
created_at          TIMESTAMP
updated_at          TIMESTAMP

UNIQUE KEY (event_key, channel, audience)
```

### `notification_dispatches` (migration 2, APPEND-ONLY)

```
id                  BIGINT UNSIGNED PK AUTO_INCREMENT
public_id           CHAR(26) UNIQUE NOT NULL            ULID
template_id         BIGINT UNSIGNED FK→notification_templates.id RESTRICT
user_id             BIGINT UNSIGNED FK→users.id RESTRICT
channel             ENUM('push','sms','whatsapp','email','in_app') NOT NULL
locale              ENUM('ar','en') NOT NULL
status              ENUM('queued','sent','delivered','failed','bounced') NOT NULL DEFAULT 'queued'
provider            VARCHAR(60) NULL                    'fcm', 'vonage', 'whatsapp_stub', 'mailchimp'
provider_ref        VARCHAR(190) NULL
sent_at             TIMESTAMP NULL
delivered_at        TIMESTAMP NULL
error_message       TEXT NULL
context             JSON NULL                           resolved variable values
reference_type      VARCHAR(120) NULL                   polymorphic source entity
reference_id        BIGINT UNSIGNED NULL
created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP  (no updated_at — append-only)

INDEX (user_id, channel, created_at)
INDEX (reference_type, reference_id)
INDEX (status, created_at)              for cleanup/retry jobs
```

> **Note:** `notification_dispatches` has no `updated_at` column — append-only per schema §0. Status-column mutations (webhook callbacks) update only `status`, `sent_at`, `delivered_at`, `error_message`, `provider_ref`.

### `notification_preferences` (migration 3)

```
id                  BIGINT UNSIGNED PK AUTO_INCREMENT
user_id             BIGINT UNSIGNED FK→users.id RESTRICT
channel             ENUM('push','sms','whatsapp','email','in_app') NOT NULL
event_category      ENUM('booking','marketing','system','chat','payment','review') NOT NULL
is_enabled          BOOLEAN NOT NULL DEFAULT true
quiet_hours_start   TIME NULL                           in user's timezone
quiet_hours_end     TIME NULL
timezone            VARCHAR(64) NULL                    fallback to users.timezone

created_at          TIMESTAMP
updated_at          TIMESTAMP

UNIQUE KEY (user_id, channel, event_category)
```

**FK dependency order:** `users` (Framework) → `notification_templates` → `notification_dispatches` → `notification_preferences`

**Critical indexes:**
- `notification_templates`: UNIQUE `(event_key, channel, audience)` — template resolution O(1)
- `notification_dispatches`: `(user_id, channel, created_at)` for user notification history; `(status, created_at)` for retry/cleanup jobs
- `notification_preferences`: UNIQUE `(user_id, channel, event_category)` — preference check O(1)

---

## 4. Per-type Coverage

Communication dispatch is **cross-type infrastructure** — the `DispatchNotificationAction` itself has no product-type branching. Per-type behavior is encoded as distinct `event_key` strings that correspond to distinct rows in `notification_templates`.

| Per-type event key | Channel(s) | Audience | Pest group |
|---|---|---|---|
| `rental.delivery_scheduled` | push, sms | customer | `rental` |
| `sale.preparation_started` | push | customer | `sale` |
| `digital.delivered` | push, email | customer | `digital` |
| `digital.expiring_soon` | push, email | customer | `digital` |

**Rule:** No `match($productType)` is needed in Communication code. The Booking/Fulfillment module fires type-specific events (`RentalDeliveryScheduled`, `SalePreparationStarted`, `DigitalDelivered`, `DigitalExpiringSoon`); each listener calls `DispatchNotificationAction` with a different hardcoded `event_key`. The dispatch action treats all event keys identically.

**Test coverage for per-type keys:**

```php
it('dispatches rental.delivery_scheduled template correctly', function () {
    // ...
})->group('communication', 'rental');

it('dispatches sale.preparation_started template correctly', function () {
    // ...
})->group('communication', 'sale');

it('dispatches digital.delivered template correctly', function () {
    // ...
})->group('communication', 'digital');

it('dispatches digital.expiring_soon template correctly', function () {
    // ...
})->group('communication', 'digital');
```

---

## 5. Locale Coverage

**Translatable fields in this phase:**

| Table | Column | Type | Required locales | Validation |
|---|---|---|---|---|
| `notification_templates` | `body` | JSON `{"en": "...", "ar": "..."}` | EN + AR both required | `required_array_keys:en,ar` + `min:1` per locale |
| `notification_templates` | `subject` | JSON `{"en": "...", "ar": "..."}` | EN + AR both required (nullable for push/SMS/WA where no subject) | nullable but if present must have both locales |

**Dispatch locale resolution:**

1. `DispatchNotificationAction` receives the target `User` model
2. Reads `$user->preferred_locale` (ENUM `'ar'|'en'`, never null per schema)
3. Passes locale to `TemplateResolver::resolve(event_key, channel, audience, locale)`
4. `TemplateResolver` calls `$template->getTranslation('body', $locale)` (spatie/laravel-translatable)
5. Writes resolved locale string to `notification_dispatches.locale`

**Filament locale coverage:**

- `NotificationTemplateResource` uses the `Translatable` trait + `filament/spatie-laravel-translatable-plugin`
- Top-bar locale switcher (EN / العربية) — admin must validate in both locales before Phase 5.0 exit

**Template seeder locale coverage (Day 1):**

All 8 × 2 = 16 event_key × audience combinations have EN + AR bodies seeded before Pest tests run. Seeder fails migration if any body is missing a locale (validation at PHP level, not DB level).

---

## 6. Idempotency

| Endpoint | Idempotency-Key? | Reason |
|---|---|---|
| `GET /api/v1/customer/notification-preferences` | No — read-only | |
| `PUT /api/v1/customer/notification-preferences/{channel}/{event_category}` | No | UPSERT by `(user_id, channel, event_category)` UNIQUE key — inherently idempotent; same request re-applied produces same state |
| `GET /api/v1/vendor/notification-preferences` | No — read-only | |
| `PUT /api/v1/vendor/notification-preferences/{channel}/{event_category}` | No | Same as customer |

**No `idempotency_keys` table entries for Phase 5.0.** The constitution's idempotency mandate applies to payment-mutating endpoints (booking submission, payment initiation, refund, withdrawal) — preference updates are pure upserts, not financial state mutations.

---

## 7. Domain Events

### Events Communication module consumes (from other modules)

| Event | Source module | Listener | Action triggered |
|---|---|---|---|
| `Booking\Events\BookingSubmitted` | Booking | `OnBookingSubmitted` | Push + email to customer; push + SMS to vendor (audience = customer + vendor) |
| `Booking\Events\BookingModified` | Booking | `OnBookingModified` | Push + email to customer (audience = customer) |
| `Booking\Events\BookingConfirmed` | Booking | `OnBookingConfirmed` | Push + email + SMS to customer; push to vendor |
| `Payments\Events\PaymentCaptured` | Payments | `OnPaymentCaptured` | Push + email to customer; push to vendor |
| `Booking\Events\RentalDeliveryScheduled` | Booking/Fulfillment | `OnRentalDeliveryScheduled` | Push + SMS to customer |
| `Booking\Events\SalePreparationStarted` | Booking/Fulfillment | `OnSalePreparationStarted` | Push to customer |
| `Booking\Events\DigitalDelivered` | Booking/Fulfillment | `OnDigitalDelivered` | Push + email to customer |
| `Booking\Events\DigitalExpiringSoon` | Booking/Fulfillment | `OnDigitalExpiringSoon` | Push + email to customer |

All 8 listeners implement `ShouldQueue` — non-blocking. All source events already fire via `DB::afterCommit()` per Booking (ADR-0004) and Payments (ADR-0005) modules.

### Events Communication module publishes

| Event | When | Fired via | Payload |
|---|---|---|---|
| `Communication\Events\NotificationDispatched` | After `notification_dispatches` row is written | `DB::afterCommit(fn () => event(...))` inside `DispatchNotificationAction` | `dispatch_id`, `user_id`, `channel`, `event_key`, `status` |

```php
// Pattern in DispatchNotificationAction::execute()
return DB::transaction(function () use ($dto) {
    $dispatch = $this->repository->create($dto);
    DB::afterCommit(fn () => NotificationDispatched::dispatch($dispatch));
    return $dispatch;
});
```

> **Note:** The `NotificationDispatched` event is published for audit and future webhook-status reconciliation. Phase 5.3 (campaigns) will listen to it for recipient tracking.

---

## 8. API Documentation Plan

### Endpoints in Phase 5.0

| Method | Path | Auth | Roles | Controller | Form Request | Resource |
|---|---|---|---|---|---|---|
| `GET` | `/api/v1/customer/notification-preferences` | sanctum-token | customer | `NotificationPreferenceController@indexCustomer` | — | `NotificationPreferenceResource[]` |
| `PUT` | `/api/v1/customer/notification-preferences/{channel}/{event_category}` | sanctum-token | customer | `NotificationPreferenceController@updateCustomer` | `UpdateNotificationPreferenceRequest` | `NotificationPreferenceResource` |
| `GET` | `/api/v1/vendor/notification-preferences` | sanctum-token | vendor | `NotificationPreferenceController@indexVendor` | — | `NotificationPreferenceResource[]` |
| `PUT` | `/api/v1/vendor/notification-preferences/{channel}/{event_category}` | sanctum-token | vendor | `NotificationPreferenceController@updateVendor` | `UpdateNotificationPreferenceRequest` | `NotificationPreferenceResource` |

### @bodyParam PHPDoc on Form Requests

`UpdateNotificationPreferenceRequest`:
```php
/**
 * @bodyParam is_enabled boolean required Whether to enable this channel for this event category. Example: true
 * @bodyParam quiet_hours_start string optional Quiet hours start time in HH:MM format (user's timezone). Example: "22:00"
 * @bodyParam quiet_hours_end string optional Quiet hours end time in HH:MM format (user's timezone). Example: "08:00"
 */
```

`{channel}` route param: ENUM `push|sms|whatsapp|email|in_app`
`{event_category}` route param: ENUM `booking|marketing|system|chat|payment|review`

**Validation rules:**
- `is_enabled` — required boolean
- `event_category != 'system'` when `is_enabled == false` → `422` with `system_notifications_cannot_be_disabled`
- `quiet_hours_start` — nullable, regex `HH:MM` (00:00–23:59)
- `quiet_hours_end` — nullable, regex `HH:MM`; required_with `quiet_hours_start`

### @response PHPDoc on Resources

`NotificationPreferenceResource` — example response (EN locale, customer):
```json
{
  "data": [
    {
      "channel": "push",
      "event_category": "booking",
      "is_enabled": true,
      "quiet_hours_start": null,
      "quiet_hours_end": null
    },
    {
      "channel": "push",
      "event_category": "marketing",
      "is_enabled": false,
      "quiet_hours_start": null,
      "quiet_hours_end": null
    },
    {
      "channel": "sms",
      "event_category": "booking",
      "is_enabled": true,
      "quiet_hours_start": "22:00",
      "quiet_hours_end": "08:00"
    }
  ],
  "meta": {},
  "errors": []
}
```

`NotificationPreferenceResource` — AR locale (same shape — preferences have no translatable text):
```json
{
  "data": [
    {
      "channel": "push",
      "event_category": "booking",
      "is_enabled": true,
      "quiet_hours_start": null,
      "quiet_hours_end": null
    }
  ],
  "meta": {},
  "errors": []
}
```

### api-registry.md Update Plan

After implementation, append to `.specify/memory/api-registry.md`:

| Method | Endpoint | Module | Phase | Auth | Roles | Request Body | Response | Documented |
|---|---|---|---|---|---|---|---|---|
| GET | /api/v1/customer/notification-preferences | Communication | 5.0 | sanctum-token | customer | — | ApiResponse{data: NotificationPreferenceResource[], meta, errors} | ✅ postman |
| PUT | /api/v1/customer/notification-preferences/{channel}/{event_category} | Communication | 5.0 | sanctum-token | customer | UpdateNotificationPreferenceRequest | ApiResponse{data: NotificationPreferenceResource, meta, errors} | ✅ postman |
| GET | /api/v1/vendor/notification-preferences | Communication | 5.0 | sanctum-token | vendor | — | ApiResponse{data: NotificationPreferenceResource[], meta, errors} | ✅ postman |
| PUT | /api/v1/vendor/notification-preferences/{channel}/{event_category} | Communication | 5.0 | sanctum-token | vendor | UpdateNotificationPreferenceRequest | ApiResponse{data: NotificationPreferenceResource, meta, errors} | ✅ postman |

### Bruno Collection

Create: `docs/api/collections/communication.bru`

```
meta {
  name: Communication — Notification Preferences
}

get {
  url: {{base_url}}/api/v1/customer/notification-preferences
  auth: bearer
  body: none
}

put {
  url: {{base_url}}/api/v1/customer/notification-preferences/push/marketing
  auth: bearer
  body: json
}

body:json {
  {
    "is_enabled": false
  }
}
```

### Postman Collection

Create: `docs/api/collections/communication.postman_collection.json`

Minimum entries:
- `GET Customer Notification Preferences` — bearer auth, `{{base_url}}/api/v1/customer/notification-preferences`
- `PUT Disable Push Marketing (Customer)` — bearer auth, body `{"is_enabled": false}`
- `GET Vendor Notification Preferences` — bearer auth, `{{base_url}}/api/v1/vendor/notification-preferences`
- `PUT Enable SMS Booking (Vendor)` — bearer auth, body `{"is_enabled": true}`

---

## 9. Packages Used

All packages are in `docs/specs/10_Package_List.md` §2 Notifications section. No new `composer require` needed.

| Package | `10_Package_List.md` §ref | Usage in Phase 5.0 |
|---|---|---|
| `kreait/laravel-firebase:^5.10` | §2 Real-time & Chat | `FcmPushAdapter` — sends FCM push messages via Firebase |
| `laravel/vonage-notification-channel:^3.3` | §2 Notifications | `VonageSmsAdapter` — sends SMS via Vonage |
| `netflie/whatsapp-cloud-api:^1.5` | §2 Notifications | `WhatsAppStubAdapter` — stub (no real API call in Phase 1) |
| `mailchimp/marketing:^3.0` | §2 Notifications | `MailchimpEmailAdapter` — sends transactional emails |
| `spatie/laravel-translatable:^6.8` | §2 Catalog & Translatable Content | `notification_templates.body` and `.subject` JSON columns |
| `spatie/laravel-permission:^6.10` | §2 Identity & Authorization | Authorization guards on preference endpoints + Filament |
| `bezhansalleh/filament-shield` | §3 Filament Plugins | `NotificationTemplateResource` permissions |
| `filament/spatie-laravel-translatable-plugin` | §3 Filament Plugins | EN/AR tabs in `NotificationTemplateResource` |
| `guzzlehttp/guzzle:^7.9` | §2 Payments | HTTP client used internally by Mailchimp SDK |

---

## 10. Architecture Tests

Add to `tests/Architecture/` (or extend existing files):

### New test: `NoCrossModuleModelImportsTest.php` — Communication section

```php
test('Communication module does not import Eloquent models from other modules')
    ->expect('App\Modules\Communication')
    ->not->toUse([
        'App\Modules\Booking\Domain\Models',
        'App\Modules\Payments\Domain\Models',
        'App\Modules\Identity\Domain\Models',
        'App\Modules\Settlement\Domain\Models',
        'App\Modules\Catalog\Domain\Models',
    ]);
```

### Extended test: `AppendOnlyTablesHaveNoSoftDeletesTest.php`

Add assertion that `NotificationDispatch` model has no `SoftDeletes` trait and no `deleted_at` cast:

```php
test('NotificationDispatch model does not use soft deletes')
    ->expect('App\Modules\Communication\Domain\Models\NotificationDispatch')
    ->not->toUse('Illuminate\Database\Eloquent\SoftDeletes');
```

### Extended test: `NoIfElseOnProductTypeStringTest.php`

```php
test('Communication module does not use if/elseif on product type strings')
    ->expect('App\Modules\Communication')
    ->not->toHaveCode("if.*product_type.*===")
    ->not->toHaveCode("elseif.*product_type.*===");
```

### New test: `EventAfterCommitTest.php` — Communication section

```php
test('DispatchNotificationAction fires NotificationDispatched after DB commit')
    ->expect('App\Modules\Communication\Application\Actions\DispatchNotificationAction')
    ->toHaveMethod('execute')
    // Manual review: confirmed DB::afterCommit() wraps event dispatch
    ->not->toUse('Illuminate\Support\Facades\Event::dispatch'); // raw dispatch (must use DB::afterCommit)
```

---

## 11. Cut-list (inherited from `docs/specs/09_Phasing_Plan.md` §PHASE 5.0)

| Feature | Decision | Target |
|---|---|---|
| WhatsApp real template calls | Deferred — `WhatsAppStubAdapter` used in Phase 1; writes `provider = 'whatsapp_stub'` dispatch row without calling Meta API | Phase 1.5 |
| Scheduled/recurring notifications | Deferred — no `scheduled_at` column or queue delay logic added | Phase 1.5 |
| `campaigns`, `campaign_runs`, `campaign_recipients` tables | Deferred — Phase 5.0 only builds the dispatch engine that campaigns depend on | Phase 5.3 |
| Mailchimp list/segment sync | Deferred — Phase 5.0 uses Mailchimp transactional API for one-off sends only; no list management | Phase 5.3 |
| In-app notification bell (Reverb websocket delivery) | Deferred — `in_app` channel dispatch row is written, but no Reverb event fired yet | Phase 5.3 |
| Delivery receipt webhooks (FCM token refresh, Vonage DLR) | Deferred — status stays `sent` until webhook registered; webhook handlers added Phase 1.5 | Phase 1.5 |

---

## Implementation Order (Day-by-Day)

### Day 1 — Schema + Channels + Core Dispatch

1. [T001] `CommunicationServiceProvider` skeleton — register in `bootstrap/providers.php`
2. [T002] Migration 1: `notification_templates`
3. [T003] Migration 2: `notification_dispatches` (append-only, no `updated_at`)
4. [T004] Migration 3: `notification_preferences`
5. [T005] `NotificationTemplate` model — translatable `body` + `subject`, casts, scopes
6. [T006] `NotificationDispatch` model — status enum cast, context array cast, `created_at` only
7. [T007] `NotificationPreference` model — UPSERT helpers, scopes
8. [T008] `NotificationChannelAdapter` contract interface
9. [T009] `FcmPushAdapter` — wraps `kreait/laravel-firebase`
10. [T010] `VonageSmsAdapter` — wraps `laravel/vonage-notification-channel`
11. [T011] `WhatsAppStubAdapter` — logs dispatch, no real API call
12. [T012] `MailchimpEmailAdapter` — wraps `mailchimp/marketing` transactional send
13. [T013] `TemplateResolver` service — single SELECT by `(event_key, channel, audience)`, returns body in requested locale
14. [T014] `DispatchNotificationAction` — preference check → template resolve → write dispatch row → queue adapter job → `DB::afterCommit` event
15. [T015] `NotificationTemplateSeeder` — 8 event keys × (customer/vendor) × 3–4 channels × EN+AR bodies

### Day 2 — Listeners + API + Filament + Tests

16. [T016] 8 event listeners (`OnBookingSubmitted`, `OnBookingModified`, `OnBookingConfirmed`, `OnPaymentCaptured`, `OnRentalDeliveryScheduled`, `OnSalePreparationStarted`, `OnDigitalDelivered`, `OnDigitalExpiringSoon`) — register in `CommunicationServiceProvider::boot()`
17. [T017] `UpdateNotificationPreferenceAction` (UPSERT)
18. [T018] `UpdateNotificationPreferenceRequest` — validation incl. `system` category guard
19. [T019] `NotificationPreferenceResource` — API Resource
20. [T020] `NotificationPreferenceController` — 4 thin methods (index + update for customer + vendor)
21. [T021] Routes: `customer.php` + `vendor.php` in `Communication/Routes/`
22. [T022] `NotificationTemplateResource` (Filament) — translatable plugin, EN/AR tabs, `is_active` toggle, `variables` key-value display
23. [T023] Run `php artisan shield:generate --all`
24. [T024] Pest: `DispatchNotificationActionTest` — happy path, locale AR, locale EN, missing template → failed dispatch
25. [T025] Pest: `NotificationPreferenceTest` — opt-out respected, system bypass, default-enabled, API 422 for system disable
26. [T026] Pest: `PerTypeEventTemplateTest` — rental/sale/digital event keys each resolve to correct template
27. [T027] Architecture tests for Communication module
28. [T028] Update `.specify/memory/api-registry.md`
29. [T029] Create `docs/api/collections/communication.bru` + `communication.postman_collection.json`
