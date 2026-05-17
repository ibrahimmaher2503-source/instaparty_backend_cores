---
## REQUIRED CONTEXT (loaded before execution)

Read before any artifact:
1. .specify/memory/constitution.md
2. .specify/memory/project-index.md
3. .specify/memory/api-registry.md
4. docs/specs/01_PRD.md
5. docs/specs/09_Phasing_Plan.md
6. docs/specs/11_DB_Schema.md
7. docs/specs/10_Package_List.md
---

# Feature Specification: Communication Provider Health

**Feature Branch**: `038-comm-provider-health`
**Created**: 2026-05-17
**Status**: Draft
**Phase**: Phase 5.3 — Communication Provider Health ⚠️ PHASE BACKFILL NEEDED (09_Phasing_Plan.md ends at Phase 5.1)

---

## Phase & PRD Alignment

**Phase ID**: Phase 5.3 — Communication Provider Health
⚠️ PHASE BACKFILL NEEDED: this micro-phase is not yet listed in `docs/specs/09_Phasing_Plan.md`.

**PRD Coverage**:
- Admin Journey §W: "Send alerts & escalations: SMS / WhatsApp / push" — this feature verifies those channels are healthy before escalations are triggered.
- FR-EXT-025 through FR-EXT-033 (local, see Functional Requirements) ⚠️ BACKFILL NEEDED: add to `docs/specs/01_PRD.md`.

**ADR**: ADR-0014 (Chat Compliance & Admin Oversight) is the closest existing ADR. No new module is introduced; this extends existing Communication module infrastructure. No new ADR is required unless a material architecture change is adopted during planning.

**Tables touched**:
- `notification_dispatches` (existing, Communication module) — schema extension: 8 new / renamed columns (⚠️ see Schema Impact section)
- No new tables.

---

## Context & Motivation

InstaParty's Communication module dispatches notifications across four channels: Firebase Cloud Messaging (push), WhatsApp, SMS (Vonage), and email (Mailchimp). The existing `NotificationChannelAdapter` contract and four provider adapters (`FcmPushAdapter`, `WhatsAppStubAdapter`, `VonageSmsAdapter`, `MailchimpEmailAdapter`) are already wired in.

Before Phase 5.3, there is no admin-facing way to:
1. Verify that a provider is reachable and credentials are valid.
2. Send a test message to a known-good address.
3. See the per-channel health at a glance (how many dispatches succeeded vs. failed in the last 24h).
4. Retry a failed dispatch without re-creating the booking event that triggered it.

Additionally, the `notification_dispatches` table lacks fields needed for robust retry orchestration: attempt count, last-attempt timestamp, next-retry timestamp, and a normalized provider-specific status code — making it impossible to implement exponential back-off or surface meaningful error codes in the UI.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — View provider health at a glance (Priority: P1)

An admin opens the Communication section of the Filament admin panel and sees a dashboard page listing the four channels (push, WhatsApp, SMS, email). For each channel the page shows: current adapter name, last-24h sent/failed counts, and the timestamp of the most recent successful dispatch. Each channel card has a visual status indicator (healthy / degraded / unknown).

**Why this priority**: Without this page, an admin has no way to detect a silently broken provider (e.g., FCM token rotated, Vonage credit expired). Missed escalation SMS to a customer because of a broken provider is a critical business risk.

**Independent Test**: Spin up a test environment with the null adapter configured, verify the health page loads, shows four channel cards, and the null adapter produces a "healthy" indicator after at least one test dispatch is sent through it.

**Acceptance Scenarios**:

1. **Given** the admin is authenticated with `view_communication_health` permission, **When** they navigate to `/admin/communication/provider-health`, **Then** they see four channel cards (push, WhatsApp, SMS, email) each showing: adapter class name (not secrets), sent count (last 24h), failed count (last 24h), last successful dispatch timestamp.
2. **Given** a channel has had zero dispatches in the last 24h, **When** the page loads, **Then** that channel shows "unknown" status and zeroed counters rather than an error.
3. **Given** a channel's failed count exceeds its sent count in the last 24h, **When** the page loads, **Then** that channel shows "degraded" status with a warning indicator.

---

### User Story 2 — Send a test message via any channel (Priority: P1)

From the health page, the admin clicks "Send Test" on any channel card. A modal prompts for a recipient address (device token for push, phone number for WhatsApp/SMS, email address for email) and an optional message body. On submit, the test dispatch is queued as a real `NotificationDispatch` record flagged as a test, so the admin can see whether it succeeds or fails in the dispatch log.

**Why this priority**: A health indicator that only shows historical data can mislead. The ability to send an on-demand test message is the only way to confirm credentials are still valid after a rotation.

**Independent Test**: Trigger `SendTestSmsAction` with a valid E.164 phone number in a feature test; assert a `NotificationDispatch` row exists with `channel=sms`, `is_test=true`, `status=queued`, and a job was pushed to the queue.

**Acceptance Scenarios**:

1. **Given** the admin enters a valid recipient and clicks "Send Test" on the push channel, **When** the action completes, **Then** a `NotificationDispatch` row exists with `is_test=true`, `channel=push`, and a queued job is dispatched; the UI shows a success notification with the `public_id` of the dispatch.
2. **Given** the admin submits a test with an invalid recipient format (e.g., a non-E.164 phone for SMS), **When** validation runs, **Then** the form shows a validation error and no dispatch record is created.
3. **Given** the null/testing adapter is active (local env), **When** any test is sent, **Then** the dispatch immediately transitions to `status=sent`, `provider_name=null_adapter`, with no external call made — confirming local/dev safety.
4. **Given** the admin sends a test, **When** viewing the dispatch log, **Then** test dispatches are visually distinguished from real dispatches (e.g., a "Test" badge) and provider secrets are never shown.

---

### User Story 3 — Retry a failed dispatch (Priority: P2)

In the Notification Dispatch log, the admin can filter by `status=failed` and click "Retry" on any row. `RetryFailedDispatchAction` increments `attempt_count`, sets `next_retry_at` to immediate, resets status to `queued`, and re-dispatches the job — without touching the original booking or notification template.

**Why this priority**: Transient provider failures (network blip, rate limit) should be recoverable without re-triggering the entire booking event chain.

**Independent Test**: Create a `NotificationDispatch` with `status=failed`, `attempt_count=1`; call `RetryFailedDispatchAction`; assert `attempt_count=2`, `status=queued`, `next_retry_at` is now, and a job is in the queue.

**Acceptance Scenarios**:

1. **Given** a dispatch has `status=failed`, **When** the admin clicks "Retry", **Then** `attempt_count` increments by 1, `status` becomes `queued`, `last_attempt_at` is updated, and a job is queued.
2. **Given** a dispatch has `status=sent` or `status=delivered`, **When** the admin views the row, **Then** the "Retry" action is not visible (only failed dispatches are retryable).
3. **Given** a dispatch has `attempt_count >= 5`, **When** the admin clicks "Retry", **Then** the system logs an audit entry and refuses with a user-facing warning: "Maximum retry attempts reached — contact platform engineering."
4. **Given** a retry job executes and the provider returns a success, **When** the job completes, **Then** `status=sent`, `provider_message_id`, `provider_status`, and `last_attempt_at` are updated on the dispatch row.

---

### User Story 4 — Scheduled automatic retry for transient failures (Priority: P3)

Failed dispatches with `next_retry_at <= now()` and `attempt_count < 5` are automatically re-queued by a scheduled job. Back-off is linear: retry at +5min, +10min, +20min, +40min, +80min. After 5 attempts the dispatch stays `failed` permanently and an audit entry is written.

**Why this priority**: Manual retry (US3) is sufficient for MVP but automatic retry eliminates the need for admin intervention on common transient failures.

**Independent Test**: Seed a failed dispatch with `next_retry_at = 1 minute ago`, run the artisan command, assert the dispatch is re-queued.

**Acceptance Scenarios**:

1. **Given** a failed dispatch with `next_retry_at` in the past and `attempt_count < 5`, **When** the scheduled command runs, **Then** the dispatch is re-queued.
2. **Given** a failed dispatch with `attempt_count = 5`, **When** the scheduled command runs, **Then** it is skipped (not re-queued) and its `next_retry_at` is set to `null`.

---

### Edge Cases

- What happens when the FCM token for a push test is expired? The adapter throws, the dispatch is marked `failed`, `provider_error_code` captures the FCM error code, `provider_error_message` captures the human-readable reason.
- What happens if `RetryFailedDispatchAction` is called concurrently for the same dispatch? The action must use a DB-level lock (`lockForUpdate`) on the dispatch row to prevent double-queuing.
- What happens when the null/testing adapter is accidentally used in production? A `config('app.env') === 'production'` guard in `NullProviderAdapter::send()` throws `RuntimeException` — it can never silently swallow a real dispatch.
- What happens if the admin sends a test message but the queue worker is down? The dispatch row is `queued` status; the test does not block the UI response. The health page will show the dispatch in `queued` state until the worker processes it.
- What happens if `provider_name` or `provider_message_id` returns more than the column allows? Adapters must truncate to column limits before writing.

---

## Requirements *(mandatory)*

### Functional Requirements

**Schema / Data**

- **FR-EXT-025**: The `notification_dispatches` table MUST be extended with the following new columns via an additive migration: `provider_name VARCHAR(60)` (replaces `provider`), `provider_message_id VARCHAR(255)` (replaces `provider_ref`), `provider_status VARCHAR(60) NULL`, `provider_error_code VARCHAR(60) NULL`, `provider_error_message TEXT NULL` (replaces `error_message`), `attempt_count TINYINT UNSIGNED NOT NULL DEFAULT 0`, `last_attempt_at TIMESTAMP NULL`, `next_retry_at TIMESTAMP NULL`, `is_test BOOLEAN NOT NULL DEFAULT FALSE`, `updated_at TIMESTAMP NULL`. The existing `provider`, `provider_ref`, and `error_message` columns must be preserved during migration and can be dropped in a future cleanup migration after confirming no production data references them.
- **FR-EXT-026**: `attempt_count` MUST increment by 1 on every dispatch attempt (initial send or retry). It is never reset to 0.

**Provider Contract**

- **FR-EXT-027**: The existing `NotificationChannelAdapter` interface MUST be extended with: `public function name(): string` (returns a short machine-readable adapter name, e.g., `"fcm"`, `"vonage_sms"`, `"mailchimp_email"`, `"whatsapp_stub"`, `"null"`) and `public function healthCheck(): ProviderHealthResult` (returns a value object with `isReachable: bool`, `latencyMs: int|null`, `note: string|null`). No new packages are permitted; `healthCheck()` should make a lightweight API call (e.g., FCM token validation, Vonage balance endpoint, Mailchimp ping) or return a mock for local/dev adapters.
- **FR-EXT-028**: A `NullProviderAdapter` MUST exist at `app/Modules/Communication/Infrastructure/Gateways/NullProviderAdapter.php`. It MUST: return `name() = "null"`, `healthCheck()` always returns `isReachable=true`, `send()` marks the dispatch `sent` in-process without any external call, and throw `RuntimeException` when `app.env === 'production'`.

**Test Send Actions**

- **FR-EXT-029**: Four dedicated Actions MUST be created: `SendTestPushAction`, `SendTestSmsAction`, `SendTestWhatsAppAction`, `SendTestEmailAction`. Each MUST: accept a typed DTO (`TestPushDTO`, `TestSmsDTO`, `TestWhatsAppDTO`, `TestEmailDTO`) containing the recipient address, optional body override, and the acting admin user ID; create a `NotificationDispatch` with `is_test=true`, `attempt_count=0`; dispatch a `DispatchNotificationJob` to the default queue; return the created `NotificationDispatch`.
- **FR-EXT-030**: Recipient address validation rules: push = non-empty string (device token); SMS = E.164 phone (`+[country][number]`); WhatsApp = E.164 phone; email = valid RFC 5322 email. Each `TestXxxDTO` enforces these rules.
- **FR-EXT-031**: Admin secrets (API keys, tokens) MUST NOT appear in any response, Filament form, or Filament infolist. The health page MUST display only: adapter `name()`, `healthCheck()` result, and aggregated dispatch counts from the database.

**Retry**

- **FR-EXT-032**: `RetryFailedDispatchAction` MUST: accept a `NotificationDispatch` with `status=failed`; throw `DispatchNotRetryableException` if `attempt_count >= 5`; use `lockForUpdate` to prevent concurrent retries; set `status=queued`, `next_retry_at=now()`, update `last_attempt_at=now()`; dispatch `DispatchNotificationJob`; write an `audit_logs` entry (`action='notification.retry_queued'`, auditable = the dispatch).
- **FR-EXT-033**: A scheduled artisan command (`communication:retry-failed-dispatches`) MUST run every 5 minutes, query dispatches where `status=failed AND attempt_count < 5 AND next_retry_at <= now()`, and call `RetryFailedDispatchAction` for each, using a `WithoutOverlapping` job lock to prevent concurrent runs.

**Filament UI**

- **FR-EXT-034**: A Filament custom page `CommunicationProviderHealthPage` MUST be registered at path `communication/provider-health` under the "Communication" navigation group with icon `heroicon-o-signal`. It MUST display: one `StatsOverviewWidget` showing 24h sent/failed counts per channel; one custom section per channel with `healthCheck()` result (from the adapter's live call); and a "Send Test" button per channel that opens a slide-over form.
- **FR-EXT-035**: The existing `NotificationDispatchResource` list page MUST add: a `SelectFilter` on `status`; a `TernaryFilter` on `is_test`; a `SelectFilter` on `provider_name`; a row `Action::make('retry')` that calls `RetryFailedDispatchAction`, visible only when `status=failed` and `attempt_count < 5`.

### Key Entities

- **NotificationDispatch** (`notification_dispatches`): Extended with provider tracking fields and retry orchestration fields. Not append-only — `status`, `attempt_count`, `last_attempt_at`, `next_retry_at`, `provider_*` fields are mutable. `updated_at` added.
- **ProviderHealthResult**: Value object (no DB table) returned by `healthCheck()`. Fields: `isReachable: bool`, `latencyMs: int|null`, `note: string|null`, `checkedAt: Carbon`.
- **TestXxxDTO** (one per channel): Carries recipient address and optional body override. Validated by corresponding `SendTestXxxRequest` FormRequest.
- **NullProviderAdapter**: Dev/test-only adapter. Registered in `AppServiceProvider` when `app.env` is `local` or `testing`. Never bound in production.

---

## Schema Impact

### `notification_dispatches` — additive migration required

⚠️ These columns are NEW — the migration adds them to the existing table. The existing `provider`, `provider_ref`, and `error_message` columns are kept (not dropped) to avoid data loss; a follow-up cleanup migration can drop them after all references are confirmed updated.

| New column | Type | Null | Notes |
|---|---|---|---|
| `provider_name` | VARCHAR(60) | YES | Replaces `provider`; adapter `name()` writes here |
| `provider_message_id` | VARCHAR(255) | YES | Replaces `provider_ref`; provider's own message/delivery ID |
| `provider_status` | VARCHAR(60) | YES | Raw provider status string (e.g., FCM `MessageId`, Vonage `0`) |
| `provider_error_code` | VARCHAR(60) | YES | Provider-specific error code for debugging |
| `provider_error_message` | TEXT | YES | Human-readable provider error; replaces `error_message` |
| `attempt_count` | TINYINT UNSIGNED | NO | DEFAULT 0; increments on every attempt |
| `last_attempt_at` | TIMESTAMP | YES | UTC; set on every dispatch attempt |
| `next_retry_at` | TIMESTAMP | YES | UTC; set by retry scheduler |
| `is_test` | BOOLEAN | NO | DEFAULT FALSE; set by test-send actions |
| `updated_at` | TIMESTAMP | YES | Added to support mutable tracking fields |

New index: `(status, next_retry_at)` for the retry scheduler query.

---

## Constitution Check

| Principle | Applies | How satisfied |
|---|---|---|
| I. Modular Monolith | YES | All new code stays under `app/Modules/Communication/` |
| II. Three Product Types | NO | Communication channels are not type-aware |
| III. Money Discipline | NO | No money fields in this feature |
| IV. Bilingual EN+AR | YES | Filament page labels and notification messages in both locales; `TestXxxDTO` body field is plain string (not translatable JSON) — test messages are admin-internal tools, not user-facing content |
| V. Append-Only Tables | YES | `notification_dispatches` is NOT append-only (status updates are allowed); confirmed correct |
| VI. Spec-Driven (ADR) | YES | No new module; ADR-0014 is the parent; no new ADR required |
| VII. Test-First | YES | Feature tests for Actions, adapter contract, retry behavior, Filament actions required |
| VIII. Idempotency | PARTIAL | Test-send actions are intentionally non-idempotent (each invocation sends a real test); retry action uses `lockForUpdate` to prevent double-queuing |
| IX. Domain Events `DB::afterCommit` | YES | `DispatchNotificationJob` dispatched after DB commit in all Actions |
| X. Vendor Approval Gate | NO | Admin-only feature; no vendor approval flow |
| XI. Document Storage | NO | No file uploads in this feature |

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Admin can confirm a provider is reachable within 30 seconds of opening the health page, without leaving the admin panel or inspecting server logs.
- **SC-002**: A test message dispatched via any of the four channels appears as a `NotificationDispatch` record with `is_test=true` within 1 second of the form submission (before the queue worker processes it).
- **SC-003**: Retry of a failed dispatch results in a re-queued job within 2 seconds of the admin clicking "Retry", with `attempt_count` incremented, observable in the dispatch log.
- **SC-004**: Failed dispatches with `attempt_count < 5` and `next_retry_at` in the past are automatically re-queued within the next 5-minute scheduler window, with zero admin intervention.
- **SC-005**: No provider API key, FCM server key, or SMTP password is visible anywhere in the admin UI or API responses — verified by a test that inspects all Filament field values rendered on the health page.
- **SC-006**: The `NullProviderAdapter` is the active provider in `local` and `testing` environments; calling any test-send action in those environments makes no outbound network request and completes successfully.
- **SC-007**: All four `SendTestXxxAction` classes and `RetryFailedDispatchAction` have Pest feature test coverage at 80%+ on their `execute()` paths.

---

## Assumptions

- Firebase Cloud Messaging, Vonage SMS, WhatsApp, and Mailchimp credentials are already configured in the `.env` file and bound in `CommunicationServiceProvider`; this feature does not introduce new credential management.
- The existing `DispatchNotificationJob` (or equivalent queued job) is the mechanism through which `NotificationChannelAdapter::send()` is invoked; the new test-send actions reuse this job rather than creating a parallel dispatch path.
- `healthCheck()` calls are synchronous within the Filament page load; if a provider API is slow to respond, the page may be slow. Async health checks (e.g., cached results refreshed by a background job) are Phase 2 optimisation.
- The `is_test` flag distinguishes test dispatches from real dispatches for UI filtering only; the underlying dispatch job treats them identically (both call the adapter).
- WhatsApp in Phase 1 uses the existing `WhatsAppStubAdapter` (no live WhatsApp Business API). The health check for WhatsApp always returns `isReachable=true` from the stub. A real WhatsApp adapter is Phase 2.
- The retry cap of 5 attempts is a reasonable default; it is not configurable via admin UI in Phase 1.
- `notification_dispatches.updated_at` being `null` on existing rows is acceptable; new rows created after this migration will have `updated_at` populated.
