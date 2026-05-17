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

CONSTRAINTS RE-ASSERTED:
- No new packages: only those in `docs/specs/10_Package_List.md`.
- No Phase 2 features (subscription tiers, dispute engine, etc.).
- Locked stack: Laravel 12 + Filament v3 + Redis queue + Sanctum admin.
- All event dispatches `DB::afterCommit`.
---

# Implementation Plan: Communication Provider Health

**Branch**: `036-admin-chat-moderation` (per `.specify/feature.json`; spec dir is `specs/038-comm-provider-health/`)
**Date**: 2026-05-17
**Spec**: [./spec.md](./spec.md)
**Phase**: Phase 5.3 — Communication Provider Health ⚠️ PHASE BACKFILL NEEDED

---

## Summary

Extend the existing Communication module with admin-facing provider health monitoring, on-demand test sends across four channels (push / WhatsApp / SMS / email), and a retry mechanism for failed `notification_dispatches`. No new packages, no new tables — only an additive migration to `notification_dispatches`, an extended `NotificationChannelAdapter` contract, a `NullProviderAdapter` for local/test environments, a new `DispatchNotificationJob`, four `SendTestXxxAction` classes, one `RetryFailedDispatchAction`, and one Filament `CommunicationProviderHealthPage`.

**Technical approach (from research)**:
1. Extend the existing `NotificationChannelAdapter` contract with `name(): string` and `healthCheck(): ProviderHealthResult`; update the four existing adapter implementations and add `NullProviderAdapter`.
2. Migrate `notification_dispatches` additively: add 8 tracking columns + `is_test` + `updated_at`, keep `provider` / `provider_ref` / `error_message` as legacy aliases (dual-write during transition).
3. Introduce `DispatchNotificationJob` (queued) that resolves the adapter and calls `send()`. New test-send actions enqueue this job; the existing `DispatchNotificationAction` is unchanged in this feature (legacy synchronous-after-commit flow preserved).
4. `RetryFailedDispatchAction` increments `attempt_count`, uses `lockForUpdate` to prevent races, dispatches `DispatchNotificationJob`, writes an `audit_logs` entry.
5. `communication:retry-failed-dispatches` artisan command runs every 5 minutes with `withoutOverlapping` to auto-retry transient failures (linear back-off encoded in `next_retry_at` at failure time).
6. `CommunicationProviderHealthPage` is a Filament custom page registered under the existing "Communication" navigation group; each channel card calls the adapter's `healthCheck()` synchronously on page load and shows 24h sent/failed counts from the database.

---

## Technical Context

**Language/Version**: PHP 8.3+
**Primary Dependencies**: Laravel 12, Filament v3, `spatie/laravel-permission`, `kreait/firebase-php` (existing), `vonage/client` (existing), `mailchimp/marketing` (existing) — all already in `10_Package_List.md`. No new packages.
**Storage**: MySQL 8 / MariaDB 11 (`notification_dispatches` table — additive migration)
**Queue**: Redis (Laravel queue, default connection); jobs use `WithoutOverlapping` middleware where appropriate
**Testing**: Pest, Laravel test helpers, `Queue::fake()`, `Bus::fake()`, factory-driven
**Target Platform**: Filament admin panel at `/admin` (web only); no API endpoints in this feature
**Project Type**: Modular monolith — feature lives under `app/Modules/Communication/`
**Performance Goals**: Health page renders in <2s with all four `healthCheck()` calls completed; retry scheduler processes 1000 failed dispatches per 5-minute window
**Constraints**: No provider secrets exposed in UI; `NullProviderAdapter` must throw if used in production; `attempt_count` capped at 5
**Scale/Scope**: Single admin panel; expected <10 simultaneous admins; <100k `notification_dispatches` per day at scale

---

## Constitution Check

*GATE: All gates must pass before Phase 0 begins. Re-checked after Phase 1 design.*

| Principle | Status | How satisfied |
|---|---|---|
| **I. Modular Monolith** | PASS | All code stays under `app/Modules/Communication/`. No cross-module model imports. Uses existing `User` only via FK on `notification_dispatches`; no `Auth::user()` dependency outside Filament context. |
| **II. Three Product Types** | N/A | Feature is not type-aware. Communication channels are orthogonal to rental/sale/digital. |
| **III. Money Discipline** | N/A | No money fields. |
| **IV. Bilingual EN+AR** | PASS | All Filament labels, validation messages, and admin notifications written through `__('communication.…')` translation keys. EN + AR `.php` files required. |
| **V. Append-Only Tables** | PASS | `notification_dispatches` is NOT in the constitution's append-only list — status/tracking columns are correctly mutable. Migration adds `updated_at` which is consistent with this. The `audit_logs` entries written by `RetryFailedDispatchAction` are themselves append-only and correctly inserted with `created_at` only. |
| **VI. Spec-Driven (ADR)** | PASS | No new module is introduced. ADR-0014 (Chat Compliance & Admin Oversight) is the parent. No new ADR required. |
| **VII. Test-First** | PASS | Day-of tests for every Action (5), adapter contract (1), provider adapters (4), scheduler command (1), Filament page (3), Filament resource action (1). |
| **VIII. Idempotency** | PASS | `RetryFailedDispatchAction` uses `lockForUpdate` to prevent double-queuing concurrent retries. Test-sends are intentionally non-idempotent (each click sends a real test). |
| **IX. Domain Events `DB::afterCommit`** | PASS | All `DispatchNotificationJob::dispatch(...)` calls wrapped in `DB::afterCommit(...)` inside Action transactions. The `NotificationDispatchRetried` event (new) fires via `afterCommit` listener. |
| **X. Vendor Approval Gate** | N/A | Admin-only feature. |
| **XI. Document Storage** | N/A | No file uploads. |

**Verdict**: All applicable gates PASS. No constitutional violations. Proceed to Phase 0.

---

## Project Structure

### Documentation (this feature)

```text
specs/038-comm-provider-health/
├── plan.md              # This file
├── spec.md              # Feature specification
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/
│   └── notification-channel-adapter.md  # Extended contract reference
├── checklists/
│   └── requirements.md  # From /speckit.specify
└── tasks.md             # Will be generated by /speckit.tasks (NOT in this run)
```

### Source Code (repository root)

```text
app/Modules/Communication/
├── Application/
│   ├── Actions/
│   │   ├── SendTestPushAction.php           # NEW
│   │   ├── SendTestSmsAction.php            # NEW
│   │   ├── SendTestWhatsAppAction.php       # NEW
│   │   ├── SendTestEmailAction.php          # NEW
│   │   └── RetryFailedDispatchAction.php    # NEW
│   ├── DTOs/
│   │   ├── TestPushDTO.php                  # NEW
│   │   ├── TestSmsDTO.php                   # NEW
│   │   ├── TestWhatsAppDTO.php              # NEW
│   │   └── TestEmailDTO.php                 # NEW
│   ├── Exceptions/
│   │   ├── DispatchNotRetryableException.php  # NEW
│   │   └── NullAdapterInProductionException.php  # NEW
│   └── Jobs/
│       └── DispatchNotificationJob.php      # NEW (queued; supersedes synchronous afterCommit path for new actions)
├── Console/
│   └── RetryFailedDispatchesCommand.php     # NEW (scheduled every 5 min)
├── Database/
│   └── Migrations/
│       └── 2026_05_17_100001_extend_notification_dispatches_for_provider_health.php  # NEW
├── Domain/
│   ├── Contracts/
│   │   └── NotificationChannelAdapter.php   # MODIFIED (add name(), healthCheck())
│   ├── Events/
│   │   └── NotificationDispatchRetried.php  # NEW
│   ├── Models/
│   │   └── NotificationDispatch.php         # MODIFIED (fillable, casts, $timestamps=true, helpers)
│   └── ValueObjects/
│       └── ProviderHealthResult.php         # NEW
├── Filament/
│   ├── Pages/                               # NEW DIR (must be added to AdminPanelProvider discoverPages)
│   │   └── CommunicationProviderHealthPage.php  # NEW
│   └── Resources/
│       └── NotificationDispatchResource.php # MODIFIED (filters + retry row action)
├── Http/
│   └── Requests/
│       └── Admin/
│           ├── SendTestPushRequest.php      # NEW
│           ├── SendTestSmsRequest.php       # NEW
│           ├── SendTestWhatsAppRequest.php  # NEW
│           └── SendTestEmailRequest.php     # NEW
├── Infrastructure/
│   └── Gateways/
│       ├── FcmPushAdapter.php               # MODIFIED (name(), healthCheck(), write to provider_name)
│       ├── VonageSmsAdapter.php             # MODIFIED (same)
│       ├── WhatsAppStubAdapter.php          # MODIFIED (same)
│       ├── MailchimpEmailAdapter.php        # MODIFIED (same)
│       └── NullProviderAdapter.php          # NEW
├── Providers/
│   └── CommunicationServiceProvider.php     # MODIFIED (register NullProviderAdapter conditionally, schedule command)
└── Resources/
    └── lang/
        ├── en/communication.php             # MODIFIED (provider_health.* keys)
        └── ar/communication.php             # MODIFIED (provider_health.* keys)

app/Providers/Filament/
└── AdminPanelProvider.php                   # MODIFIED (add discoverPages line for Communication module)

tests/Feature/Modules/Communication/ProviderHealth/
├── ContractTest.php                                # NEW — every adapter implements name() + healthCheck() correctly
├── NullProviderAdapterTest.php                     # NEW — succeeds locally, throws in prod env
├── SendTestPushActionTest.php                      # NEW
├── SendTestSmsActionTest.php                       # NEW
├── SendTestWhatsAppActionTest.php                  # NEW
├── SendTestEmailActionTest.php                     # NEW
├── RetryFailedDispatchActionTest.php               # NEW — retry, lock, cap, audit log
├── RetryFailedDispatchesCommandTest.php            # NEW — scheduler picks up due dispatches
├── CommunicationProviderHealthPageTest.php         # NEW — page renders, no secrets exposed, stats accurate
└── NotificationDispatchResourceRetryActionTest.php # NEW — UI retry button visibility + behavior
```

**Structure Decision**: Standard modular-monolith layout under `app/Modules/Communication/`. The only structural addition outside the module is a single `discoverPages` line in `AdminPanelProvider.php` to expose the new Pages directory (consistent with how other modules register pages — see Catalog, Payments, Reviews already listed in `AdminPanelProvider.php` lines 78-86).

---

## Phase Outputs Index

- **Phase 0 output**: [research.md](./research.md) — 6 decisions resolved (queue refactor strategy, health check approaches per provider, retry back-off, migration approach, null adapter binding strategy, audit log action key).
- **Phase 1 output**: [data-model.md](./data-model.md) — full schema of extended `notification_dispatches` + DTO/VO shapes; [contracts/notification-channel-adapter.md](./contracts/notification-channel-adapter.md) — extended interface contract; [quickstart.md](./quickstart.md) — manual QA flow for verifying the feature works end-to-end.
- **Agent context update**: claude (via `update-agent-context.ps1 -AgentType claude`).

---

## Complexity Tracking

> No Constitution violations to justify. Section intentionally minimal.

| Decision | Why this rather than the simpler default |
|---|---|
| Introduce `DispatchNotificationJob` instead of reusing the existing `DB::afterCommit(...) -> adapter->send()` pattern | The spec explicitly requires queue-based dispatch (FR-EXT-029) so test sends and retries do not block the request thread or the queue worker that picked up the booking event. Keeping the existing flow untouched avoids regressions in 30+ listener-driven dispatch paths; only the new test/retry paths use the queued job. |
| Keep legacy `provider`, `provider_ref`, `error_message` columns and dual-write during transition | A column rename in MySQL requires a deploy-coordinated change. Dual-writing for the lifetime of this phase keeps reads working from any old code path (analytics, logs) and lets a future cleanup migration drop the legacy columns in one shot. |
| Bind `NullProviderAdapter` only in `local`/`testing` environments | Production safety: a misconfigured `app.env` should never silently drop real notifications. The adapter additionally throws if its `send()` is called while `app()->environment('production')` — defense in depth. |

---

## Post-Design Constitution Re-check

After completing Phase 1 (data-model, contracts, quickstart): no new violations introduced. All gates remain PASS. The data model is purely additive on `notification_dispatches`; the contract extension is backwards-compatible (new methods added, no signatures changed); the queued job adds a queue dependency that is already in use elsewhere.

**Ready for `/speckit.tasks`.**
