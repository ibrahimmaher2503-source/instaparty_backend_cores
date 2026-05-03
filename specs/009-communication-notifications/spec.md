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
- Every generated artifact must cite specific FR numbers from 01_PRD.md
- Every generated artifact must cite specific table names from 11_DB_Schema.md
- Every generated artifact must align to a Phase ID from 09_Phasing_Plan.md
- Never suggest a package not in 10_Package_List.md
- Never suggest a Phase 2 feature
- Never contradict docs/specs/02_Tech_Decisions.md locked stack

API DOCUMENTATION CONSTRAINT:
- Every API endpoint generated must include:
  - @bodyParam PHPDoc on every Form Request field (Scribe-compatible)
  - @response PHPDoc with realistic EN+AR example data on every Resource
  - Entry added to .specify/memory/api-registry.md
  - Bruno/Postman collection entry in docs/api/collections/
---

# Feature Specification: Phase 5.0 — Communication: Notifications

**Feature Branch**: `009-communication-notifications`
**Phase**: 5.0 — Communication: Notifications (2 days, Week 6)
**Created**: 2026-05-03
**Status**: Draft
**ADR**: [ADR-0010 — Communication Module](../../docs/adr/0010-communication-module.md) — **Accepted**

---

## Phase Identity

| Field | Value |
|---|---|
| **Phase ID** | 5.0 (per `docs/specs/09_Phasing_Plan.md` §PHASE 5.0) |
| **PRD Coverage** | FR-23, FR-24, FR-25, FR-26 (Marketing Campaign channel integrations) + PRD §6.1 step 11 (booking confirmation notifications) |
| **Tables touched** | `notification_templates`, `notification_dispatches`, `notification_preferences` |
| **ADR reference** | ADR-0010 — Communication Module (Accepted 2026-05-03) |
| **Week** | Week 6, Days 1–2 |
| **Blocks** | Phase 5.1 (Reviews — sends notifications), Phase 5.2 (Loyalty — sends notifications), Phase 5.3 (Marketing Campaigns — uses dispatch infrastructure) |

---

## User Scenarios & Testing

### User Story 1 — Customer receives booking lifecycle notifications (Priority: P1)

When a customer completes a payment or their booking changes state, they receive real-time notifications via push and email in their preferred language (Arabic or English), so they are always informed without having to check the app manually.

**Why this priority**: Core to the customer journey (PRD §6.1 step 11). Without this, customers have no feedback loop after submitting or paying for a booking. All downstream phases depend on the notification channel being live.

**Independent Test**: Can be fully tested by creating a booking, firing the `PaymentCaptured` event, and asserting that a push notification and email dispatch record exist in `notification_dispatches` with `locale = users.preferred_locale`.

**Acceptance Scenarios**:

1. **Given** a customer with `preferred_locale = 'ar'` has a booking confirmed and payment captured, **When** the `PaymentCaptured` event fires, **Then** two `notification_dispatches` rows are created — one for `push` and one for `email` — both with `locale = 'ar'` and `status = 'queued'`, and the push adapter is called with the Arabic body from `notification_templates`.

2. **Given** the `booking.submitted` event fires for a booking with a customer who has `preferred_locale = 'en'`, **When** `DispatchNotificationAction` runs, **Then** the English body from the matching `notification_templates` row is used, and the dispatch row has `locale = 'en'`.

3. **Given** a template exists for `(event_key = 'booking.modified', channel = 'push', audience = 'customer')`, **When** a vendor modifies a booking item, **Then** a push notification is queued for the customer with the modified booking's public_id in the `context` JSON.

4. **Given** no template row exists for a given `(event_key, channel, audience)` combination, **When** `DispatchNotificationAction` is called, **Then** a `notification_dispatches` row is written with `status = 'failed'` and `error_message` explaining the missing template — no exception is thrown to the caller.

---

### User Story 2 — Vendor receives notifications for incoming bookings (Priority: P1)

When a new booking request is routed to a vendor, or when a confirmed booking transitions to a fulfillment milestone, the vendor receives a push and SMS notification in their preferred locale so they can act within the 24-hour SLA.

**Why this priority**: Vendor response SLA is 24 hours (per schema decision in `11_DB_Schema.md`). Vendors must be notified immediately to avoid SLA breaches.

**Independent Test**: Fire `BookingSubmitted` event → assert vendor receives push + SMS dispatches.

**Acceptance Scenarios**:

1. **Given** a booking is submitted with vendor A assigned, **When** `BookingSubmitted` fires, **Then** a `push` and `sms` dispatch are created for vendor A using the vendor audience template for `booking.submitted`.

2. **Given** a rental booking reaches `delivery_scheduled` fulfillment state, **When** `RentalDeliveryScheduled` fires, **Then** a push + SMS dispatch is queued for the customer using the `rental.delivery_scheduled` template.

3. **Given** a digital service booking, **When** `DigitalDelivered` fires, **Then** a push + email dispatch is queued for the customer using the `digital.delivered` template.

4. **Given** a digital service with `expiry_days_after_purchase = 3` and 1 day remaining, **When** `DigitalExpiringSoon` fires, **Then** a push + email dispatch is queued for the customer using the `digital.expiring_soon` template, with the expiry date in the `context` JSON.

---

### User Story 3 — Customer manages notification preferences (Priority: P2)

A customer can opt out of marketing notifications on specific channels (push, SMS, email) while keeping booking and system notifications active, so they control their communication experience without losing critical updates.

**Why this priority**: User opt-outs are required for regulatory compliance and user trust. Marketing spam is a fast path to app uninstalls.

**Independent Test**: Set `notification_preferences` row for `(user, push, marketing)` to `is_enabled = false`, fire a marketing event, assert no push dispatch was created.

**Acceptance Scenarios**:

1. **Given** a customer has disabled push for the `marketing` event category, **When** a marketing notification dispatch is attempted, **Then** no `notification_dispatches` row is created for push, and the dispatch is silently skipped.

2. **Given** a customer has disabled push for the `marketing` category, **When** a `booking.confirmed` event fires (category = `booking`), **Then** the push dispatch IS created because `booking` is a different event category than `marketing`.

3. **Given** a customer attempts to disable notifications for the `system` event category, **When** the preference update is submitted, **Then** the API returns `422 Unprocessable Entity` with error `system_notifications_cannot_be_disabled`.

4. **Given** no preference row exists for a user, **When** any notification event fires, **Then** the dispatch proceeds as if `is_enabled = true` (default-enabled behavior per schema decision).

---

### User Story 4 — Admin manages notification templates (Priority: P2)

An admin can view, edit, and activate/deactivate notification templates from the Filament admin panel — editing both English and Arabic bodies — so that the platform can update notification wording without a code deployment.

**Why this priority**: Template content will need updates (tone, wording, added variables). Admin-editable templates prevent code releases for copy changes.

**Independent Test**: Admin opens `NotificationTemplateResource` in Filament, edits the Arabic body for `booking.confirmed/push/customer`, saves, then fires the event and asserts the new body appears in the dispatch's `context`.

**Acceptance Scenarios**:

1. **Given** an admin with `update_notification_template` permission, **When** they update the Arabic body of a template and save, **Then** the next dispatch using that template uses the new body.

2. **Given** an admin deactivates a template (`is_active = false`), **When** the corresponding event fires, **Then** no dispatch is created for that channel (treated as missing template → `status = 'failed'` row).

3. **Given** an admin creates a new template without an Arabic body, **When** they try to save, **Then** the form validation rejects the save and shows a bilingual error.

---

### User Story 5 — Per-type fulfillment event templates resolve correctly (Priority: P3)

The system resolves the correct template for rental, sale, and digital fulfillment events, so each product type's customers receive contextually relevant notifications that match their booking type.

**Why this priority**: Per-type events are a Phase 5.0 exit criterion. Incorrect resolution would send rental customers sale wording.

**Independent Test**: Seed templates for `rental.delivery_scheduled`, `sale.preparation_started`, `digital.delivered`. Fire each event and assert each dispatch links to the correct template.

**Acceptance Scenarios**:

1. **Given** templates exist for `rental.delivery_scheduled`, `sale.preparation_started`, and `digital.delivered`, **When** each corresponding event fires, **Then** `notification_dispatches.template_id` links to the exact matching template (not a different type's template).

2. **Given** only a `rental.delivery_scheduled` template exists for push, **When** `SalePreparationStarted` fires, **Then** no push dispatch is created (no fallback to rental template), and a `status = 'failed'` dispatch row logs the missing template.

---

### Edge Cases

- What happens when a user's device token in `user_devices` is expired or invalid? → FCM returns an error; the dispatch row is updated to `status = 'failed'` with the FCM error in `error_message`. The listener retries once via queue; after failure it stays `failed`.
- What if `DispatchNotificationAction` is called for a user with `status = 'banned'`? → Dispatch is skipped; no row written.
- What if the same `(event_key, channel, audience)` template exists in both `is_active = true` and `false` state due to a race condition? → The UNIQUE constraint on `(event_key, channel, audience)` prevents this — there can be exactly one template per combination.
- What if `preferred_locale` is set but the template only has one locale populated? → Both locales are required at template creation time (validation); this state cannot exist in a healthy system.
- What if the queue worker is down when an event fires? → The `notification_dispatches` row is written immediately by `DispatchNotificationAction` with `status = 'queued'`; the queue job retries when the worker recovers.
- What if WhatsApp Cloud API returns a rate-limit error? → The stub never calls the real API in Phase 1. In Phase 1.5, the adapter catches rate-limit errors and marks the dispatch `status = 'failed'` for retry.

---

## Requirements

### Functional Requirements

- **FR-5.0.01**: System MUST fire push and email notifications to customers on `booking.submitted`, `booking.modified`, `booking.confirmed`, and `payment.captured` events.
- **FR-5.0.02**: System MUST fire push and SMS notifications to vendors on `booking.submitted` and `booking.confirmed` events.
- **FR-5.0.03**: System MUST fire per-type notifications: `rental.delivery_scheduled` (push + SMS), `sale.preparation_started` (push), `digital.delivered` (push + email), `digital.expiring_soon` (push + email).
- **FR-5.0.04**: System MUST select notification body based on `users.preferred_locale` (`en` or `ar`) from `notification_templates`.
- **FR-5.0.05**: System MUST record every dispatch attempt in `notification_dispatches` (append-only, status-updatable).
- **FR-5.0.06**: System MUST check `notification_preferences` before dispatching — skip if `is_enabled = false` for that `(user, channel, event_category)`.
- **FR-5.0.07**: System MUST NOT allow disabling the `system` event category in `notification_preferences`.
- **FR-5.0.08**: System MUST fall back to `is_enabled = true` when no preference row exists for a user.
- **FR-5.0.09**: Template resolution failure MUST NOT throw an exception — it MUST write a `failed` dispatch row and continue.
- **FR-5.0.10**: Admin MUST be able to create, update, activate, and deactivate notification templates via Filament with bilingual (EN + AR) body editing.
- **FR-5.0.11**: System MUST support four channel adapters: FCM push, Vonage SMS, WhatsApp (stub in Phase 1), Mailchimp email.
- **FR-5.0.12**: WhatsApp adapter MUST be a stub in Phase 1 — it writes a dispatch row with `provider = 'whatsapp_stub'` and does NOT call the external API.
- **FR-5.0.13**: `notification_templates` seed MUST include both EN and AR bodies for `booking.submitted`, `booking.modified`, `booking.confirmed`, `payment.captured` (all channels, both audience = customer and vendor).
- **FR-5.0.14**: Customers and vendors MUST be able to read and update their notification preferences via API endpoints.
- **FR-5.0.15**: All dispatch jobs MUST run on the queue — dispatches are non-blocking for the event originator.
- **FR-5.0.16** (PRD FR-23): The push channel adapter infrastructure MUST be ready to support admin-defined push campaigns (foundation for Phase 5.3).
- **FR-5.0.17** (PRD FR-24): The WhatsApp channel adapter (even as stub) MUST be in place to support admin-defined WA campaigns (foundation for Phase 5.3).
- **FR-5.0.18** (PRD FR-25): The SMS channel adapter MUST be ready to support admin-defined SMS campaigns (foundation for Phase 5.3).
- **FR-5.0.19** (PRD FR-26): The email channel adapter MUST be ready to support admin-defined email campaigns via Mailchimp (foundation for Phase 5.3).

### Key Entities

- **NotificationTemplate**: Defines the body (EN + AR JSON), subject (EN + AR JSON), channel, audience, and event key for one type of notification. UNIQUE per `(event_key, channel, audience)`. Contains `variables` JSON documenting available placeholder tokens (e.g., `{{booking_id}}`, `{{vendor_name}}`).
- **NotificationDispatch**: Immutable record of one notification send attempt. Links to template, user, and the source entity (polymorphic `reference_type` / `reference_id`). Status progresses from `queued` → `sent` → `delivered` (or `failed` / `bounced`). Stores the resolved `context` JSON (actual variable values used).
- **NotificationPreference**: Per-user setting for one `(channel, event_category)` pair. Absent row = enabled by default. `system` category cannot be set to disabled.

---

## API Endpoints

| Method | Path | Auth | Roles | Purpose |
|---|---|---|---|---|
| `GET` | `/api/v1/customer/notification-preferences` | Sanctum | customer | List all preference rows for the authenticated customer |
| `PUT` | `/api/v1/customer/notification-preferences/{channel}/{event_category}` | Sanctum | customer | Enable or disable a specific channel × category for the customer |
| `GET` | `/api/v1/vendor/notification-preferences` | Sanctum | vendor | List all preference rows for the authenticated vendor |
| `PUT` | `/api/v1/vendor/notification-preferences/{channel}/{event_category}` | Sanctum | vendor | Enable or disable a specific channel × category for the vendor |

> **Note:** Admin template management is Filament-only (no REST API endpoint). Notification dispatch is internal (event-driven, no external trigger endpoint).

**PUT Request Body** (`/notification-preferences/{channel}/{event_category}`):
- `is_enabled` — boolean, required. Whether to enable or disable this preference.
- `quiet_hours_start` — time (HH:MM), optional. Quiet hours start (user's timezone).
- `quiet_hours_end` — time (HH:MM), optional. Quiet hours end (user's timezone).

---

## Success Criteria

- [ ] **SC-001**: Booking event fires → push + email dispatch records created in `notification_dispatches` within the same queue flush cycle (no missed events in integration test).
- [ ] **SC-002**: Arabic-locale customer receives Arabic notification body; English-locale customer receives English body — verified in Pest locale tests.
- [ ] **SC-003**: All 4 channel adapters (FCM, Vonage, WhatsApp stub, Mailchimp) can dispatch without throwing uncaught exceptions — verified via mock adapter tests.
- [ ] **SC-004**: Per-type event keys (`rental.delivery_scheduled`, `sale.preparation_started`, `digital.delivered`, `digital.expiring_soon`) each resolve to their correct distinct template — verified in Pest per-type tests.
- [ ] **SC-005**: Preference opt-out is respected: disabling `push/marketing` does not block `push/booking` dispatches — verified in Pest preference tests.

---

## Constitution Check

| Principle | Status | Reasoning |
|---|---|---|
| **I. Modular Monolith** | ✅ PASS | All 9 Communication tables owned by `app/Modules/Communication/`. Cross-module access only via `NotificationDispatcher` contract and domain event listeners — no direct model imports from other modules. |
| **II. Three Product Types** | ✅ PASS | Communication dispatch engine is cross-type. Per-type behavior is encoded as distinct `event_key` strings in `notification_templates`. No `if/elseif` on type strings; `match($enum)` is not needed in Communication — the event key already encodes type. Pest tests cover all three types' event keys explicitly. |
| **III. Money Discipline** | ✅ N/A | No money columns in this phase. |
| **IV. Bilingual EN+AR** | ✅ PASS | `notification_templates.body` and `.subject` are JSON translatable columns. Both `en` and `ar` are required at template creation (Filament form validation + API validation). Dispatch uses `users.preferred_locale` to select body. Pest tests assert both locales. |
| **V. Append-Only Tables** | ✅ PASS | `notification_dispatches` is append-only — only `status`, `sent_at`, `delivered_at`, `error_message`, `provider_ref` are mutable post-creation. No `softDeletes()` on this table. Schema decision confirmed in `11_DB_Schema.md`. |
| **VI. ADR Before Code** | ✅ PASS | ADR-0010 — Communication Module created and marked Accepted on 2026-05-03, before any migration or code. |
| **VII. Test-First for Critical Paths** | ✅ PASS | Pest tests are Day 2 work (same day as actions/listeners per constitution). Coverage targets: dispatch happy path, locale, per-type keys, preference opt-out, system category bypass. |
| **VIII. Idempotency** | ✅ N/A | No state-changing payment/booking mutation endpoints in this phase. Notification preference update (`PUT`) is idempotent by nature (upsert). No `idempotency_keys` table entry needed. |
| **IX. Domain Events `DB::afterCommit`** | ✅ PASS | All listeners (`OnBookingSubmitted`, `OnPaymentCaptured`, etc.) are queued listeners triggered by domain events from Booking/Payments/Settlement — they already fire after `DB::afterCommit` per those modules' ADRs. |
| **X. Vendor Approval Gate** | ✅ N/A | Notification dispatch does not gate on vendor approval — it is a read-only consumer of events already filtered by Booking/Payments. |
| **XI. Document Storage** | ✅ N/A | No file uploads in this phase. |

---

## Cut-list (inherited from `docs/specs/09_Phasing_Plan.md` §PHASE 5.0)

- **Deferred to Phase 1.5:** WhatsApp real templates — Phase 1 uses `WhatsAppStubAdapter` only.
- **Deferred to Phase 1.5:** Scheduled/recurring notifications (e.g., "your event is tomorrow" reminders).
- **Deferred to Phase 5.3:** Marketing campaign engine (`campaigns`, `campaign_runs`, `campaign_recipients` tables) — Phase 5.0 only builds the dispatch infrastructure they depend on.
- **Deferred to Phase 5.3:** Mailchimp list/segment sync — Phase 5.0 implements the email adapter for transactional sends only.

---

## Assumptions

- `kreait/laravel-firebase` (FCM adapter), `laravel/vonage-notification-channel` (SMS), `netflie/whatsapp-cloud-api` (WA stub), and `mailchimp/marketing` (email) are already in `docs/specs/10_Package_List.md` §2 Notifications section — confirmed.
- Device tokens for push are available via `user_devices` table (Identity module). Communication will call `Identity`'s `UserDeviceRepository` contract to fetch them — no direct model import.
- `users.preferred_locale` is always `'ar'` or `'en'` (ENUM constraint in schema) — no fallback logic needed.
- Booking and Payments domain events (`BookingSubmitted`, `BookingModified`, `BookingConfirmed`, `PaymentCaptured`) are already defined in those modules' codebases (Phase 3.x + 4.x complete). Communication only adds listeners.
- Per-type fulfillment events (`RentalDeliveryScheduled`, `SalePreparationStarted`, `DigitalDelivered`, `DigitalExpiringSoon`) will be added to the Booking module's event registry as part of Phase 5.0 Day 1 work, fired from fulfillment state transition listeners.
- The Filament admin panel is configured to auto-discover resources from `app/Modules/*/Filament/Resources/` — no manual registration needed.
- `notification_dispatches` is NOT in the append-only restriction for `updated_at` — the table has `created_at` only (per `11_DB_Schema.md` append-only convention). Status-field mutations via webhook callbacks do not require `updated_at`.
