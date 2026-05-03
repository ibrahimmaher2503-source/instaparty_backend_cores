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

# Feature Specification: Phase 5.3 — Marketing Campaigns

**Feature Branch**: `012-marketing-campaigns`
**Phase**: 5.3 — Marketing Campaigns (1 day, Week 7)
**Created**: 2026-05-03
**Status**: Draft
**ADR**: [ADR-0010 — Communication Module](../../docs/adr/0010-communication-module.md) — **Accepted** (no new ADR required; campaigns extend the Communication module already governed by ADR-0010)

---

## Phase Identity

| Field | Value |
|---|---|
| **Phase ID** | 5.3 (per `docs/specs/09_Phasing_Plan.md` §PHASE 5.3) |
| **PRD Coverage** | FR-23 (Push campaigns), FR-24 (WhatsApp campaigns), FR-25 (SMS campaigns), FR-26 (Email campaigns via Mailchimp), FR-27 (separation of platform scope from external provider usage costs) |
| **Tables touched** | `campaigns`, `campaign_runs`, `campaign_recipients` |
| **Tables read (no schema change)** | `users`, `customer_profiles`, `vendor_profiles`, `bookings`, `booking_items`, `notification_templates`, `notification_dispatches`, `notification_preferences`, `user_devices` |
| **ADR reference** | ADR-0010 — Communication Module (Accepted 2026-05-03). Campaigns are a sub-capability of Communication; no new module ADR required. |
| **Week** | Week 7, Day 1 |
| **Blocks** | None (last Communication-track phase before Reporting/CMS hardening) |
| **Depends on** | Phase 5.0 (dispatch infrastructure: `DispatchNotificationAction`, channel adapters FCM/Vonage/WhatsApp-stub/Mailchimp, `notification_preferences` enforcement) |

---

## Overview

Phase 5.3 builds the **Marketing Campaign Builder** so admins can compose a one-shot, segment-targeted message and dispatch it through the four channels already wired in Phase 5.0 (push, SMS, WhatsApp stub, email). The builder is a Filament-only admin tool — no customer-facing or vendor-facing API. Campaigns reuse the `DispatchNotificationAction` so opt-out rules in `notification_preferences` (event_category = `marketing`) are honored automatically.

The canonical scenario from `09_Phasing_Plan.md`:

> Admin creates "10% off Rentals this week" campaign → dispatches to all customers who booked rentals in last 30 days → segments by locale → sends correct template.

---

## User Scenarios & Testing

### User Story 1 — Admin builds and sends a segmented marketing campaign (Priority: P1)

An admin opens the Filament Campaign Builder, names the campaign, picks a channel (push / SMS / WhatsApp / email), composes a bilingual subject and body, defines a segment (e.g., "customers who booked rentals in the last 30 days"), and clicks "Send now". The system resolves the segment, queues per-recipient dispatch jobs, and reports how many were sent / failed / skipped.

**Why this priority**: This is the entire deliverable for Phase 5.3. Without it, FR-23..FR-27 are unfulfilled. Every other story in this phase is a refinement of this core flow.

**Independent Test**: Seed 10 customers — 4 with bookings of `product_type = rental` in the last 30 days, 6 without — open the Filament builder, target the rental segment, send a push campaign, then assert exactly 4 `campaign_recipients` rows exist for that `campaign_run_id` and 4 `notification_dispatches` rows were created.

**Acceptance Scenarios**:

1. **Given** an admin with `create_campaign` permission, **When** they fill the Campaign Builder with name "10% off Rentals", channel `push`, target locale `both`, segment `{ "booked_product_type": "rental", "booked_within_days": 30 }`, EN body "10% off rentals this week", AR body "خصم 10% على التأجير هذا الأسبوع", and click "Send now", **Then** a `campaigns` row is created with `status = 'running'`, exactly one `campaign_runs` row is created, and one `campaign_recipients` row is created per matched customer.

2. **Given** the campaign above is dispatched, **When** the queue worker processes the run, **Then** every matched customer with `users.preferred_locale = 'ar'` receives a push using the AR body, every customer with `preferred_locale = 'en'` receives the EN body, and `campaign_recipients.dispatch_id` links to the corresponding `notification_dispatches` row.

3. **Given** a campaign with `target_locale = 'ar'`, **When** the run executes, **Then** only users with `preferred_locale = 'ar'` are included as recipients; users with `preferred_locale = 'en'` are excluded entirely (no `campaign_recipients` row written for them).

4. **Given** the run completes, **When** `campaign_runs` is read, **Then** `recipients_total`, `recipients_sent`, `recipients_failed` reflect the final counts and `completed_at` is set; the parent `campaigns.status` transitions to `completed`.

---

### User Story 2 — Marketing opt-outs are respected (Priority: P1)

A customer who has disabled the `marketing` event category on the chosen channel does not receive the campaign — they are recorded as `skipped` rather than `sent`, and no provider call is made for them.

**Why this priority**: Regulatory and trust requirement. Sending to opted-out users on real channels (SMS/email) violates compliance and burns provider credits (FR-27 cost discipline). Opt-out enforcement is the single largest source of brand damage in marketing.

**Independent Test**: Create one customer with `notification_preferences (push, marketing) is_enabled = false`, include them in the segment, run the campaign, and assert their `campaign_recipients.status = 'skipped'` and no `notification_dispatches` row was created for them.

**Acceptance Scenarios**:

1. **Given** customer A has `notification_preferences (channel = 'push', event_category = 'marketing', is_enabled = false)` and matches the segment, **When** the campaign run executes, **Then** customer A's `campaign_recipients` row is written with `status = 'skipped'` and `dispatch_id = NULL`, and `recipients_failed` is NOT incremented (skipped is distinct from failed).

2. **Given** customer B has no preference row at all and matches the segment, **When** the run executes, **Then** customer B is dispatched normally (default-enabled fallback) — `status = 'sent'` once the channel adapter confirms.

3. **Given** customer C has disabled `marketing` for `email` only, **When** an `sms` marketing campaign runs and includes customer C, **Then** customer C is dispatched normally (preference is per-channel × per-category).

4. **Given** an admin attempts to use the campaign builder to send a campaign with `event_category` other than `marketing`, **When** they save, **Then** the form rejects with a validation error — campaigns dispatched through the Campaign Builder are always `marketing` category and cannot bypass opt-outs.

---

### User Story 3 — Segment resolver matches customers by booking history and product type (Priority: P1)

The segment resolver supports a small, fixed set of filter keys for Phase 1: `booked_product_type` (rental | sale | digital), `booked_within_days` (integer), `governorate_id` (FK), and `preferred_locale` (ar | en | both). Combinations use AND semantics. The resolver returns a deduplicated list of `user_id`s.

**Why this priority**: Without segment resolution, every campaign is a blast to all customers — useless and abusive. The "10% off Rentals" canonical scenario hinges on the segment resolver finding the right customers.

**Independent Test**: Seed customers with various booking histories (rental within 30d, sale within 30d, rental 60d ago, no bookings), call the segment resolver with `{ booked_product_type: 'rental', booked_within_days: 30 }`, and assert the returned set matches exactly the rental-within-30d customers.

**Acceptance Scenarios**:

1. **Given** customers C1 (rental booking 5 days ago), C2 (sale booking 10 days ago), C3 (rental booking 60 days ago), C4 (no bookings), **When** the resolver runs with `{ booked_product_type: 'rental', booked_within_days: 30 }`, **Then** the returned set is exactly `[C1]`.

2. **Given** the same customers and `{ booked_within_days: 30 }` (no product type filter), **When** the resolver runs, **Then** the returned set is `[C1, C2]` (both booked within 30d, regardless of type).

3. **Given** `{ governorate_id: 5, booked_product_type: 'digital' }`, **When** the resolver runs, **Then** only customers in governorate 5 with at least one digital booking (any time) are returned.

4. **Given** a customer with two qualifying bookings, **When** the resolver runs, **Then** they appear exactly once in the recipient set (deduplication by `user_id`).

5. **Given** an empty `segment_filters` (`{}`), **When** the resolver runs, **Then** the form rejects the save — campaigns must target a non-empty segment to prevent accidental "send to all" blasts. (Phase 1 cut-list: explicit "all customers" segment is not exposed.)

---

### User Story 4 — All four channels dispatch correctly (Priority: P1)

The campaign runner can send through `push` (FCM), `sms` (Vonage), `whatsapp` (Phase 1 stub), and `email` (Mailchimp). Each per-channel adapter is the same one used by Phase 5.0 transactional notifications — campaigns are not a parallel implementation.

**Why this priority**: PRD FR-23..FR-26 each call out a separate channel. Missing any one channel fails the exit criterion "All 4 channels dispatch."

**Independent Test**: Run four single-recipient campaigns (one per channel), and assert that each campaign produces a `notification_dispatches` row with the correct `channel` and `provider` value (`fcm`, `twilio` or `vonage`, `whatsapp_stub`, `mailchimp`).

**Acceptance Scenarios**:

1. **Given** a `push` campaign with one matching recipient who has a registered FCM device token, **When** the run executes, **Then** a `notification_dispatches` row exists with `channel = 'push'`, `provider = 'fcm'`, and the FCM adapter was invoked.

2. **Given** an `sms` campaign with one matching recipient who has a verified phone, **When** the run executes, **Then** a `notification_dispatches` row exists with `channel = 'sms'`, `provider = 'vonage'`.

3. **Given** a `whatsapp` campaign in Phase 1, **When** the run executes, **Then** a `notification_dispatches` row exists with `channel = 'whatsapp'`, `provider = 'whatsapp_stub'`, and **no** real WhatsApp Cloud API call is made (FR-27: Phase 1 separates platform scope from external provider costs — WA real send is Phase 1.5).

4. **Given** an `email` campaign, **When** the run executes, **Then** a `notification_dispatches` row exists with `channel = 'email'`, `provider = 'mailchimp'`, and the Mailchimp transactional send adapter is invoked.

---

### User Story 5 — Campaign run is observable from Filament (Priority: P2)

Admins can open a campaign and see its run progress in real time (or near real time): how many recipients were resolved, how many sent, how many failed, how many skipped (opt-out). They can also drill into individual recipients to see the dispatch status.

**Why this priority**: Without observability, admins cannot tell whether a campaign worked. Required for trust in the system, but the underlying counts are aggregations of `campaign_recipients` rows already written — no new persistence.

**Independent Test**: Run a campaign with 3 recipients (1 sent, 1 failed, 1 skipped), open the Filament `CampaignResource` detail page, and assert the visible totals match (1, 1, 1) and the recipients sub-table lists each user with the correct status.

**Acceptance Scenarios**:

1. **Given** a completed campaign run with 10 recipients (7 sent, 2 failed, 1 skipped), **When** an admin opens the campaign in Filament, **Then** the page shows `recipients_total = 10`, `recipients_sent = 7`, `recipients_failed = 2`, plus a derived "skipped = 1" count.

2. **Given** a campaign in `running` state, **When** an admin opens it, **Then** the live counts increment as the queue worker progresses (poll-based refresh acceptable in Phase 1; real-time streaming is out of scope).

3. **Given** an admin clicks into a recipient row, **When** the linked `notification_dispatches` row exists, **Then** they see the per-recipient `status`, `provider_ref`, `error_message`, and the resolved EN/AR body that was sent.

---

### Edge Cases

- **Segment resolves to zero recipients.** The campaign is still created but immediately transitions to `status = 'completed'` with `recipients_total = 0`; no run row is created and no jobs are queued. The admin sees an explicit "0 recipients matched" warning in the Filament notification toast after save.
- **Recipient has no contact info for the chosen channel.** E.g., a `push` campaign targets a customer with no `user_devices` row, or an `sms` campaign targets a customer without a verified phone. The recipient is recorded as `failed` with `error_message = 'no_contact_for_channel'`; the segment resolver does NOT pre-filter on contact availability (so admins see the gap and can decide whether to retry on a different channel).
- **Customer is suspended/banned.** Excluded by the segment resolver — banned users never appear in any campaign. (Identity module already enforces this on `users.status`.)
- **Vendor user matches the segment by accident.** Phase 1 segments target customers only — `audience` on the underlying template is hard-coded to `customer`. Vendor users are excluded by joining only `customer_profiles`.
- **Same customer matches via two segment filters.** Deduplicated to one `campaign_recipients` row.
- **Admin clicks "Send now" twice within 5 seconds.** The Filament action is `requiresConfirmation()` and disabled while the campaign is in `running` state — the second click is a no-op. Idempotency on the action handler additionally guards against double-dispatch.
- **Queue worker crashes mid-run.** `campaign_recipients` rows already in `queued` state remain so; the run is `running` indefinitely. An admin can use a "Retry queued recipients" Filament action to re-enqueue them. Auto-recovery is Phase 1.5 (cut-listed).
- **Mailchimp / FCM provider returns a hard error for a single recipient.** That recipient's `notification_dispatches.status = 'failed'`, the `campaign_recipients.status = 'failed'`, the run continues for other recipients. `recipients_failed` is incremented.
- **Quiet hours overlap with send time.** Phase 1 ignores quiet hours for marketing campaigns (admin's "Send now" is intentional). Quiet hours apply to transactional notifications only. Documented in Assumptions.
- **Admin tries to schedule for the future.** The `scheduled_at` column exists on the `campaigns` table but Phase 1 hides the field in the Filament form — only "Send now" is exposed. (Cut-listed to Phase 1.5.)
- **Campaign body references a missing variable** (e.g., `{{first_name}}` for a customer with NULL `first_name`). The variable resolves to an empty string; no dispatch fails for templating reasons. Variables are intentionally minimal in Phase 1 (see FR-5.3.13).

---

## Requirements

### Functional Requirements

- **FR-5.3.01** *(PRD FR-23)*: Admin MUST be able to create a `push` campaign via the Filament Campaign Builder, defining a name, segment, target locale, EN+AR subject (optional), EN+AR body (required), and dispatching it through the existing FCM channel adapter.
- **FR-5.3.02** *(PRD FR-24)*: Admin MUST be able to create a `whatsapp` campaign through the same builder; in Phase 1 the WA channel uses the stub adapter (`provider = 'whatsapp_stub'`) — no real Cloud API call.
- **FR-5.3.03** *(PRD FR-25)*: Admin MUST be able to create an `sms` campaign through the same builder, dispatching via the Vonage adapter wired in Phase 5.0.
- **FR-5.3.04** *(PRD FR-26)*: Admin MUST be able to create an `email` campaign through the same builder, dispatching via the Mailchimp transactional send adapter wired in Phase 5.0.
- **FR-5.3.05** *(PRD FR-27)*: External provider usage costs (FCM device messaging fees, Vonage SMS credits, Mailchimp send credits) MUST NOT be embedded in any platform-side calculation, ledger, or commission. Campaigns log only platform-side metadata; provider invoices are reconciled out-of-band.
- **FR-5.3.06**: Campaigns MUST be one-shot — `status` lifecycle: `draft` → `running` → `completed | failed | cancelled`. No recurring schedule in Phase 1 (cut-listed).
- **FR-5.3.07**: System MUST support exactly these segment filter keys in Phase 1: `booked_product_type` (rental | sale | digital), `booked_within_days` (positive integer), `governorate_id` (FK to `governorates`), and `preferred_locale` (ar | en | both). Unknown keys in `segment_filters` JSON MUST cause a validation error at campaign save time.
- **FR-5.3.08**: System MUST resolve segments at run time (not at draft time). The recipient set is materialized into `campaign_recipients` only when the run starts.
- **FR-5.3.09**: System MUST exclude users with `users.status` of `suspended` or `banned` from every campaign segment automatically (no admin override).
- **FR-5.3.10**: System MUST select per-recipient body language using `users.preferred_locale`. If the campaign's `target_locale` is `ar` or `en`, only users matching that locale are included; if `both`, users of either locale are included and each receives their preferred-locale body.
- **FR-5.3.11**: System MUST honor `notification_preferences.is_enabled = false` for `(channel, event_category = 'marketing')` — those recipients are recorded as `campaign_recipients.status = 'skipped'` with no provider call.
- **FR-5.3.12**: Campaigns dispatched via the Campaign Builder MUST always use `event_category = 'marketing'` — there is no path to bypass marketing opt-outs through this builder.
- **FR-5.3.13**: Campaign body MAY reference a fixed Phase 1 variable set: `{{first_name}}`, `{{preferred_locale}}`, `{{governorate_name_localized}}`. Missing values resolve to empty string. The Filament form MUST list available variables inline as helper text.
- **FR-5.3.14**: System MUST persist a `campaigns` row at draft time (admin saves before sending). The row's `status` stays `draft` until the admin clicks "Send now".
- **FR-5.3.15**: On "Send now", System MUST atomically: (a) flip `campaigns.status` to `running`, (b) create one `campaign_runs` row, (c) resolve the segment, (d) write `campaign_recipients` rows in `queued` state, (e) queue per-recipient dispatch jobs. All within `DB::transaction`; jobs queued via `DB::afterCommit`.
- **FR-5.3.16**: System MUST update `campaign_runs.recipients_sent` and `recipients_failed` as each per-recipient job completes. `recipients_total` is set once at run start.
- **FR-5.3.17**: When all per-recipient jobs have terminated (sent/failed/skipped), System MUST set `campaign_runs.completed_at` and flip `campaigns.status` to `completed`. If any of the underlying dispatch jobs encountered an unrecoverable system failure (e.g., the entire queue worker crashed), an admin retry action is required — no auto-completion of orphan runs.
- **FR-5.3.18**: Each per-recipient dispatch MUST go through `DispatchNotificationAction` (the Phase 5.0 contract) — campaigns MUST NOT bypass this layer to call channel adapters directly.
- **FR-5.3.19**: System MUST link each `campaign_recipients.dispatch_id` to the `notification_dispatches` row created by `DispatchNotificationAction` for that recipient.
- **FR-5.3.20**: Filament Campaign Builder MUST validate EN and AR bodies are both non-empty before allowing save (consistent with Constitution §IV bilingual rule).
- **FR-5.3.21**: Admin MUST be able to view a campaign's recipients list (paginated, filterable by status) from the Filament campaign detail page.
- **FR-5.3.22**: Admin MUST be able to cancel a campaign in `draft` state (deletes the row) or in `running` state (marks `status = 'cancelled'`, prevents new per-recipient jobs from enqueueing; jobs already in flight complete normally).
- **FR-5.3.23**: System MUST emit a domain event `MarketingCampaignDispatched(campaign_id, run_id, recipients_total)` on run completion, fired via `DB::afterCommit` (per Constitution §IX). No external listeners are required in Phase 1; the event is recorded for future analytics.

### Key Entities

- **Campaign** (`campaigns`): An admin-defined marketing message with channel, target locale, segment filters, bilingual subject + body, and a lifecycle status. UNIQUE per `public_id`. One Campaign has zero or one `campaign_runs` row in Phase 1 (no recurring runs). `created_by` references the admin user.
- **CampaignRun** (`campaign_runs`): A single execution of a campaign — captures the materialized run-time stats (totals sent/failed/queued at completion). Append-only conceptually; only `started_at`, `completed_at`, `recipients_sent`, `recipients_failed` mutate during the run.
- **CampaignRecipient** (`campaign_recipients`): One row per `(campaign_run, user)`. Holds per-recipient status (`queued | sent | failed | skipped`) and a nullable link to the `notification_dispatches` row produced by `DispatchNotificationAction`.

---

## API Endpoints

Phase 5.3 has **no customer-facing or vendor-facing API** — campaign management is Filament-only.

| Method | Path | Auth | Roles | Purpose |
|---|---|---|---|---|
| *(none)* | *(none)* | *(N/A)* | *(N/A)* | All campaign workflows live in the Filament admin panel under Communications → Campaigns. |

> **Why**: PRD FR-23..FR-27 explicitly scope marketing campaigns to admin operations. Customer-side opt-out is already exposed by the Phase 5.0 `notification-preferences` endpoints; nothing new is needed here.
>
> **Filament resource:** `app/Modules/Communication/Filament/Resources/CampaignResource.php`. Uses `filament/spatie-laravel-translatable-plugin` for the EN/AR tabs on `subject` + `body`. Permissions generated via `php artisan shield:generate --all` per `.claude/rules/filament.md`.

---

## Success Criteria

### Measurable Outcomes

- **SC-001**: Admin can compose a campaign in the Filament builder and dispatch it to a non-empty matched segment in **under 5 minutes** end-to-end (open builder → save → send → see "running" status).
- **SC-002**: For a 1,000-recipient run, **100% of segment-matched eligible customers** appear as `campaign_recipients` rows within 60 seconds of the run starting (segment resolution must scale to phase-1 customer base).
- **SC-003**: For a campaign with `target_locale = 'both'`, **every** AR-locale recipient receives the AR body and **every** EN-locale recipient receives the EN body — verified by a Pest test asserting `notification_dispatches.context.body_locale` matches `users.preferred_locale` for every recipient.
- **SC-004**: For a customer with `notification_preferences (channel, marketing) is_enabled = false`, the corresponding `campaign_recipients` row has `status = 'skipped'` and **zero** provider calls are made on their behalf — verified by Pest with mocked adapters that fail loudly if invoked.
- **SC-005**: Each of the four channels (`push`, `sms`, `whatsapp`, `email`) successfully produces a `notification_dispatches` row with the correct `provider` value when targeted in a single-recipient run — verified per channel by Pest.
- **SC-006**: An admin viewing a completed campaign in Filament sees `recipients_total = recipients_sent + recipients_failed + recipients_skipped` (math reconciles).

---

## Constitution Check

| Principle | Status | Reasoning |
|---|---|---|
| **I. Modular Monolith** | ✅ PASS | All three campaign tables and the Filament resource live under `app/Modules/Communication/`. Segment resolution reads `bookings`/`booking_items` only via a `BookingHistoryReader` contract from `Booking/Domain/Contracts/` — no direct cross-module Eloquent imports. |
| **II. Three Product Types** | ✅ PASS | `segment_filters.booked_product_type` uses `App\Modules\Catalog\Domain\Enums\ProductType` (cast at validation time). The segment query uses `match($enum)` to map to the appropriate booking-history reader method when needed. No `if/elseif` on type strings. Pest covers all three product-type segments. |
| **III. Money Discipline** | ✅ N/A | No money columns added. `campaigns` does not store costs; FR-5.3.05 explicitly forbids embedding provider costs in platform ledgers. |
| **IV. Bilingual EN+AR** | ✅ PASS | `campaigns.subject` and `campaigns.body` are JSON translatable columns. The Filament form uses the translatable plugin's EN/AR tabs and validates both bodies are non-empty (FR-5.3.20). Per-recipient locale selection is via `users.preferred_locale` (FR-5.3.10). Pest asserts both locales. |
| **V. Append-Only Tables** | ✅ PASS (with note) | `campaigns` and `campaign_runs` are status-mutable (per `09_Phasing_Plan.md` and `11_DB_Schema.md`) — `campaigns.status` and `campaign_runs.{started_at, completed_at, recipients_*}` are the only mutable fields. `campaign_recipients` mutates `status` + `dispatch_id` only after creation. None of the three tables are in the strict append-only list (`§5` constitution); they have `created_at`/`updated_at` per `11_DB_Schema.md`. |
| **VI. ADR Before Code** | ✅ PASS | Communication module is governed by ADR-0010 (Accepted 2026-05-03). Marketing Campaigns is a sub-capability of that module; no new ADR required. The campaign tables are listed under Communication in `11_DB_Schema.md` §11. |
| **VII. Test-First for Critical Paths** | ✅ PASS | Phase 5.3 is a 1-day phase; tests are written same-day per Constitution §VII. Coverage targets: segment resolution per filter combination (incl. all three product types), per-locale body selection, marketing opt-out skip, all four channel adapters, send-now happy path. |
| **VIII. Idempotency** | ✅ PASS | The "Send now" Filament action is guarded against double-click by Filament's confirmation modal AND a status-state check (running campaigns reject re-dispatch). The per-recipient dispatch jobs go through `DispatchNotificationAction` which is already designed to be safely re-runnable (writes a single dispatch row per call). No `idempotency_keys` table entry needed because there is no external API endpoint. |
| **IX. Domain Events `DB::afterCommit`** | ✅ PASS | `MarketingCampaignDispatched` is fired via `DB::afterCommit` on run completion (FR-5.3.23). The "Send now" Action queues per-recipient jobs only after the transaction creating the run + recipients commits. |
| **X. Vendor Approval Gate** | ✅ N/A | Campaigns target customers only; vendor approval gating is not relevant. |
| **XI. Document Storage** | ✅ N/A | No file uploads. Campaign assets (images for push/email) are out of scope in Phase 1 — bodies are text-only. (Cut-listed to Phase 1.5.) |

---

## Cut-list (inherited from `docs/specs/09_Phasing_Plan.md` §PHASE 5.3)

- **Deferred to Phase 2:** A/B testing — variants, holdout groups, lift measurement.
- **Deferred to Phase 1.5:** Scheduling future sends — Phase 1 exposes "Send now" only. The `campaigns.scheduled_at` column exists in schema but the Filament form hides it.
- **Deferred to Phase 1.5:** Recurring campaigns (weekly/monthly drip).
- **Deferred to Phase 1.5:** WhatsApp real send (Phase 1 uses stub adapter only — FR-5.3.02).
- **Deferred to Phase 1.5:** Mailchimp list/segment sync (campaigns dispatch via Mailchimp transactional only; bulk list management out of scope).
- **Deferred to Phase 1.5:** Image attachments / rich HTML email templates — Phase 1 bodies are plain text + minimal placeholders.
- **Deferred to Phase 1.5:** Quiet-hours suppression for marketing (transactional already respects them).
- **Deferred to Phase 1.5:** Auto-recovery of orphaned runs (manual admin retry only in Phase 1).
- **Deferred to Phase 1.5:** "Send to all customers" segment (Phase 1 requires non-empty `segment_filters` to prevent accidental blasts — FR-5.3.07).
- **Deferred to Phase 8.3:** Offer Governance UI on top of campaigns (per `09_Phasing_Plan.md` §PHASE 8.3 — campaign-backed offers).

---

## Assumptions

- Phase 5.0 (Communication: Notifications) is **complete and merged** before Phase 5.3 starts. Specifically: `notification_templates`, `notification_dispatches`, `notification_preferences` tables exist; `DispatchNotificationAction` is callable; FCM/Vonage/WhatsApp-stub/Mailchimp adapters are wired; `notification_preferences` enforcement runs in `DispatchNotificationAction`.
- ADR-0010 (Communication Module) covers the campaign sub-capability — no new ADR is filed.
- Customer base in Phase 1 is small enough (low thousands) that segment resolution with `IN (...)` queries on `bookings`/`booking_items` does not require a denormalized read model. Phase 2 may introduce a marketing CDP if scale demands it.
- Campaigns target the `customer` audience only in Phase 1. Vendor-targeted campaigns (e.g., "new feature launched") are Phase 1.5 and require a separate audience selector in the form.
- The four channel adapters' provider names in `notification_dispatches.provider` are stable: `fcm`, `vonage` (or `twilio` if the Phase 5.0 implementation chose Twilio — verify against `09_communication-notifications/plan.md` before implementation), `whatsapp_stub`, `mailchimp`.
- The Filament admin panel auto-discovers resources from `app/Modules/*/Filament/Resources/` (per `AdminPanelProvider`) — no manual registration needed.
- The `campaigns` table allows `event_category` to be implicit `'marketing'` — it is not stored on the row because every Campaign Builder dispatch is marketing by definition. The category is passed to `DispatchNotificationAction` as a literal `'marketing'`.
- Provider usage costs (SMS credits, email sends, push fees) are tracked in the providers' own dashboards and reconciled out-of-band by ops — there is no platform-side ledger of campaign costs (FR-5.3.05 / PRD FR-27).
- The Filament shield permission generator will produce `view_any_campaign`, `create_campaign`, `update_campaign`, `delete_campaign`, and a custom `dispatch_campaign` permission for the "Send now" action. Only `super_admin` and `marketing_admin` roles will receive `dispatch_campaign`.
- Pest tests for this phase live under `tests/Feature/Modules/Communication/Campaigns/`. Architecture tests (no cross-module model imports) already exist from Phase 5.0 and will catch any regression.
