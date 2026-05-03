# ADR-0010 — Communication Module

- **Status:** Accepted
- **Date:** 2026-05-03
- **Decision-makers:** Ibrahim
- **Tags:** module, phase-5-communication
- **Related:**
  - [`docs/adr/0001-modular-monolith-pattern.md`](0001-modular-monolith-pattern.md) — parent pattern
  - [`docs/specs/01_PRD.md`](../specs/01_PRD.md) §7.6 (FR-23–FR-27), §6.1 step 11
  - [`docs/specs/02_Tech_Decisions.md`](../specs/02_Tech_Decisions.md) §1 (Real-time + Push stack)
  - [`docs/specs/11_DB_Schema.md`](../specs/11_DB_Schema.md) §11 (9 Communication tables)
  - [`docs/specs/10_Package_List.md`](../specs/10_Package_List.md) §2 (Notifications section)

---

## 1. السياق / Context

**EN:** InstaParty must notify customers, vendors, and admins at every key event in the booking lifecycle — submission, modification, confirmation, payment capture, and type-specific fulfillment milestones. The platform also needs the infrastructure to run admin-initiated marketing campaigns across push, email, SMS, and WhatsApp. Without a centralised communication module, notification logic would scatter into Booking, Payments, and Settlement listeners, making template management and per-user preferences impossible to maintain.

**Phase:** 5.0 (Notifications core) + 5.3 (Marketing Campaigns)
**Built in:** Week 6, Days 1–2 (Phase 5.0); Week 7, Day 1 (Phase 5.3)

---

## 2. المسؤوليات / Responsibilities

### This module owns:

- All outbound notification templates and their bilingual content (EN + AR)
- Notification dispatch log (`notification_dispatches`) — append-only record of every send attempt
- Per-user notification preferences (channel + event category opt-in/out; `system` can never be disabled)
- Chat thread audit metadata and message moderation log (Phase 5.0+ chat audit)
- Marketing campaign definitions, runs, and recipient tracking (Phase 5.3)
- All channel adapters: FCM push, Vonage SMS, WhatsApp Cloud API (stub in Phase 1), Mailchimp email

### This module does NOT own:

- Push device tokens → owned by Identity (`user_devices`)
- The events that trigger notifications → owned by Booking, Payments, Settlement (they fire domain events; Communication listens)
- Chat message content → stored in Firebase Firestore; this module only mirrors metadata for moderation
- User locale/timezone preferences → owned by Identity (`users.preferred_locale`, `users.timezone`)

---

## 3. الجداول المملوكة / Tables Owned

From [`docs/specs/11_DB_Schema.md`](../specs/11_DB_Schema.md) §11:

| Table | Purpose | Soft-delete? | Append-only? |
|---|---|---|---|
| `notification_templates` | Template definitions per (event_key × channel × audience) | No | No |
| `notification_dispatches` | Immutable record of every notification sent/attempted | No | Yes (status only) |
| `notification_preferences` | Per-user per-channel per-category opt-in/out | No | No |
| `chat_threads` | Audit metadata for Firestore chat threads | No | No |
| `chat_message_log` | Append-only mirror of Firestore messages for moderation | No | Yes |
| `chat_moderation_flags` | Admin moderation actions on chat messages | No | No |
| `campaigns` | Marketing campaign definitions | No | No |
| `campaign_runs` | Execution records for each campaign | No | No |
| `campaign_recipients` | Per-recipient dispatch tracking within a run | No | Yes |

**Foreign key dependencies:**

| FK | References | Module |
|---|---|---|
| `notification_dispatches.user_id` | `users.id` | Framework |
| `notification_dispatches.template_id` | `notification_templates.id` | Communication (self) |
| `notification_preferences.user_id` | `users.id` | Framework |
| `chat_threads.customer_id` | `users.id` | Framework |
| `chat_threads.vendor_profile_id` | `vendor_profiles.id` | Identity |
| `chat_threads.booking_id` | `bookings.id` | Booking |
| `chat_message_log.sender_id` | `users.id` | Framework |
| `campaign_recipients.user_id` | `users.id` | Framework |
| `campaign_recipients.dispatch_id` | `notification_dispatches.id` | Communication (self) |

---

## 4. هل الـ module type-aware؟ / Is this module type-aware?

**☑ Partially type-aware (per-type event keys only)**

The Communication module itself is cross-cutting infrastructure — the dispatch engine, channel adapters, and template CRUD are identical for all product types. However, the **event keys** that trigger notifications include type-specific variants:

- `rental.delivery_scheduled`, `rental.setup_started`, `rental.teardown_completed`
- `sale.preparation_started`, `sale.dispatched`, `sale.delivered`
- `digital.delivered`, `digital.expiring_soon`, `digital.expired`

These are handled as distinct template rows in `notification_templates` (one row per event_key × channel × audience). No `match($enum)` branching is needed in the dispatch Action itself — the `event_key` string already encodes the type. The Booking and Fulfillment modules fire type-specific domain events; the Communication module listens to them without needing to know the product type logic.

**Consequence:** Pest tests must verify that templates resolve correctly for all three types' event keys, but dispatch logic itself is uniform.

---

## 5. Layer Layout

```
app/Modules/Communication/
├── Domain/
│   ├── Models/
│   │   ├── NotificationTemplate.php
│   │   ├── NotificationDispatch.php
│   │   ├── NotificationPreference.php
│   │   ├── ChatThread.php
│   │   ├── ChatMessageLog.php
│   │   ├── ChatModerationFlag.php
│   │   ├── Campaign.php
│   │   ├── CampaignRun.php
│   │   └── CampaignRecipient.php
│   ├── Enums/
│   │   ├── NotificationChannel.php    # push, sms, whatsapp, email, in_app
│   │   ├── NotificationAudience.php   # customer, vendor, admin
│   │   ├── DispatchStatus.php         # queued, sent, delivered, failed, bounced
│   │   └── EventCategory.php          # booking, marketing, system, chat, payment, review
│   ├── Events/
│   │   └── NotificationDispatched.php
│   └── Contracts/
│       ├── NotificationChannelAdapter.php    # interface all channel adapters implement
│       └── NotificationDispatcher.php        # public contract other modules may call
├── Application/
│   ├── Actions/
│   │   ├── DispatchNotificationAction.php    # resolves template, checks preferences, queues send
│   │   └── UpdateNotificationPreferenceAction.php
│   ├── Services/
│   │   └── TemplateResolver.php              # (event_key × channel × audience × locale) → body
│   ├── DTOs/
│   │   ├── DispatchNotificationDTO.php
│   │   └── NotificationPreferenceDTO.php
│   └── Listeners/
│       ├── OnBookingSubmitted.php
│       ├── OnBookingModified.php
│       ├── OnBookingConfirmed.php
│       ├── OnPaymentCaptured.php
│       ├── OnRentalDeliveryScheduled.php
│       ├── OnSalePreparationStarted.php
│       ├── OnDigitalDelivered.php
│       └── OnDigitalExpiringSoon.php
├── Infrastructure/
│   ├── Repositories/
│   │   ├── EloquentNotificationTemplateRepository.php
│   │   └── EloquentNotificationPreferenceRepository.php
│   └── Gateways/
│       ├── FcmPushAdapter.php          # kreait/laravel-firebase
│       ├── VonageSmsAdapter.php        # laravel/vonage-notification-channel
│       ├── WhatsAppStubAdapter.php     # netflie/whatsapp-cloud-api (stub for Phase 1)
│       └── MailchimpEmailAdapter.php   # mailchimp/marketing
├── Http/
│   ├── Controllers/
│   │   └── NotificationPreferenceController.php
│   ├── Requests/
│   │   └── UpdateNotificationPreferenceRequest.php
│   └── Resources/
│       └── NotificationPreferenceResource.php
├── Filament/
│   └── Resources/
│       └── NotificationTemplateResource.php
├── Routes/
│   ├── customer.php
│   └── vendor.php
├── Database/
│   ├── Migrations/
│   └── Seeders/
│       └── NotificationTemplateSeeder.php
├── Resources/
│   └── lang/
│       ├── en/
│       └── ar/
└── Providers/
    └── CommunicationServiceProvider.php
```

---

## 6. القرارات الداخلية / Internal Decisions

### 6.1 Channel Adapters via `NotificationChannelAdapter` Contract

**Decision:** All channel adapters implement a single `NotificationChannelAdapter` interface with `send(NotificationDispatch $dispatch): void`. The adapter is resolved from the service container by `DispatchNotificationAction` based on the dispatch channel enum.

**Alternative rejected:** Using Laravel's built-in `Notification` class with per-channel `via()` + `toFcm()` / `toVonage()` methods on a Notification class. This approach tightly couples the notification payload construction to the dispatch mechanism and makes adding new channels harder.

**Why:** The `NotificationChannelAdapter` pattern keeps channel-specific SDKs isolated in `Infrastructure/Gateways/`. The `Application/Actions/DispatchNotificationAction` stays clean and testable without mocking FCM or Vonage. Swapping SMS providers (e.g., Vonage → local Egyptian gateway) only requires a new adapter class, not touching any Action.

### 6.2 WhatsApp Stubbed in Phase 1

**Decision:** `WhatsAppStubAdapter` logs the would-be message to `notification_dispatches` with `status = 'sent'` and `provider = 'whatsapp_stub'` but never calls the WhatsApp Cloud API.

**Why:** WhatsApp Business template approval is an external process (Meta review) that can block development. The stub lets us test the full dispatch flow end-to-end without external dependency. Phase 1.5 replaces the stub with `netflie/whatsapp-cloud-api`.

### 6.3 `notification_dispatches` is Append-Only

**Decision:** Only `status`, `sent_at`, `delivered_at`, `error_message`, and `provider_ref` can be mutated after row creation — all other columns are written once. No soft deletes.

**Why:** The dispatch log is an audit trail. Deleting or modifying a dispatch record would undermine the audit trail and make debugging failed notifications impossible. Webhook callbacks from FCM/Vonage update only status columns.

### 6.4 Template Resolution Strategy

**Decision:** `TemplateResolver` performs a single indexed lookup by `(event_key, channel, audience)`. There is no fallback chain (e.g., no "fall through to a generic template if locale is missing"). Both `en` and `ar` bodies are required at template creation time.

**Why:** A fallback chain introduces silent degradation (user gets an English notification even though they prefer Arabic) that is hard to debug. Requiring both locales upfront via validation ensures we never send untranslated content.

### 6.5 Notification Preference Check Before Dispatch

**Decision:** `DispatchNotificationAction` checks `notification_preferences` before queuing a dispatch. If no preference row exists, the default is `enabled = true`. The `system` event category always dispatches regardless of preference rows.

**Why:** Respecting user opt-outs is a regulatory requirement (CAN-SPAM equivalent, GDPR-adjacent). The "no row = enabled" default avoids having to seed 5 preference rows per user at registration.

---

## 7. Inter-Module Communication

### Events we publish:

| Event | When | Payload |
|---|---|---|
| `Communication\NotificationDispatched` | After dispatch is written to `notification_dispatches` | `dispatch_id`, `user_id`, `channel`, `event_key` |

### Events we consume:

| Event | Source Module | Action |
|---|---|---|
| `Booking\BookingSubmitted` | Booking | `OnBookingSubmitted` → dispatch push + email to customer + vendor |
| `Booking\BookingModified` | Booking | `OnBookingModified` → dispatch push + email to customer |
| `Booking\BookingConfirmed` | Booking | `OnBookingConfirmed` → dispatch push + email + SMS to customer + vendor |
| `Payments\PaymentCaptured` | Payments | `OnPaymentCaptured` → dispatch push + email to customer + vendor |
| `Booking\RentalDeliveryScheduled` | Booking/Fulfillment | `OnRentalDeliveryScheduled` → dispatch push + SMS to customer |
| `Booking\SalePreparationStarted` | Booking/Fulfillment | `OnSalePreparationStarted` → dispatch push to customer |
| `Booking\DigitalDelivered` | Booking/Fulfillment | `OnDigitalDelivered` → dispatch push + email to customer |
| `Booking\DigitalExpiringSoon` | Booking/Fulfillment | `OnDigitalExpiringSoon` → dispatch push + email to customer |

### Public Contracts (interfaces we expose):

| Contract | Purpose | Implementation |
|---|---|---|
| `Communication\Domain\Contracts\NotificationDispatcher` | Other modules call `dispatch(string $eventKey, User $recipient, array $context)` without knowing channel logic | `DispatchNotificationAction` |

### Public Contracts (interfaces we consume):

| Contract | From Module | Why we need it |
|---|---|---|
| `Identity\Domain\Contracts\UserDeviceRepository` | Identity | Fetch FCM device tokens for a user |

---

## 8. Filament Footprint

| Resource | Purpose | Translatable? |
|---|---|---|
| `Communication/Filament/Resources/NotificationTemplateResource.php` | Admin CRUD for notification templates — body (EN+AR), active toggle, variables docs | Yes |

**Navigation group:** `Communications`

**Permissions generated by Shield:**
- `view_any_notification_template`, `view_notification_template`, `create_notification_template`, `update_notification_template`, `delete_notification_template`

---

## 9. Testing Strategy

**Pest groups:** `communication`, `notifications`

**Test files:**
```
tests/
├── Feature/Modules/Communication/
│   ├── DispatchNotificationActionTest.php
│   ├── NotificationPreferenceTest.php
│   └── PerTypeEventTemplateTest.php
└── Unit/Modules/Communication/
    └── TemplateResolverTest.php
```

**Required coverage:**
- [x] Happy path: event fires → template resolved → dispatch queued → adapter called
- [x] Locale: dispatch uses `users.preferred_locale` (test AR + EN)
- [x] Preference opt-out: `marketing` category disabled → notification skipped
- [x] `system` category: never skipped regardless of preference
- [x] Per-type event keys: `rental.delivery_scheduled`, `sale.preparation_started`, `digital.delivered` each resolve to correct template
- [x] Missing template: `DispatchNotificationAction` logs failure to `notification_dispatches` with `status = 'failed'`, does not throw
- [x] WhatsApp stub: dispatch row created with `provider = 'whatsapp_stub'`, no external call

---

## 10. Cut-list (if behind schedule)

From [`docs/specs/09_Phasing_Plan.md`](../specs/09_Phasing_Plan.md) §Phase 5.0:

- [ ] WhatsApp real templates → defer to Phase 1.5 (use `WhatsAppStubAdapter`)
- [ ] Scheduled/recurring notifications → defer to Phase 1.5
- [ ] Marketing campaigns (`campaigns`, `campaign_runs`, `campaign_recipients`) → Phase 5.3

---

## 11. Open Questions

All questions resolved before accepting this ADR.

---

## 12. Implementation Checklist

- [ ] Migrations in `Communication/Database/Migrations/` (notification_templates → notification_dispatches → notification_preferences)
- [ ] Models under `Domain/Models/` (relationships, casts, scopes ONLY)
- [ ] `NotificationChannelAdapter` contract + 4 adapters in `Infrastructure/Gateways/`
- [ ] `DispatchNotificationAction` + `TemplateResolver` + `UpdateNotificationPreferenceAction`
- [ ] 8 event listeners wired in `CommunicationServiceProvider::boot()`
- [ ] `NotificationTemplateSeeder` with EN+AR bodies for 4 core events + 4 per-type events
- [ ] API routes: GET + PUT `/notification-preferences` for customer + vendor
- [ ] `NotificationTemplateResource` Filament resource
- [ ] `CommunicationServiceProvider` registered in `bootstrap/providers.php`
- [ ] Pest tests — all coverage items above
- [ ] `php artisan shield:generate --all`
- [ ] Translations in `Resources/lang/en/` and `Resources/lang/ar/`
- [ ] This ADR added to [`docs/adr/README.md`](README.md)
