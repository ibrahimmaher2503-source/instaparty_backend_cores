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
- FR traceability per spec.md (FR-EXT-036-*).
- Schema traceability — flag missing migrations even when schema doc has the table.
- Phase alignment — Phase 8.2.
- Never suggest a package not in 10_Package_List.md.
- Never suggest a Phase 2 feature.
- Never contradict docs/specs/02_Tech_Decisions.md locked stack.
- Inviolable: no code path may UPDATE chat_message_log content fields, no admin path may DELETE chat content.
---

# Implementation Plan: Admin Restricted Chat Moderation UI

**Branch**: `036-admin-chat-moderation` | **Date**: 2026-05-16 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/036-admin-chat-moderation/spec.md`

## Summary

Deliver the admin moderation surface for Phase 8.2 over the locked Firestore-mirror schema. Firestore listener writes append-only rows to `chat_message_log`; a queued `DetectSuspiciousMessageJob` runs the curated regex set (phone / email / external link, with Eastern-Arabic digit normalization) and inserts `chat_moderation_flags` on match. Three new Filament resources — `ChatThreadResource` (view-only index + Infolist), `ChatMessageLogResource` (read-only list/view), `ChatModerationFlagResource` (view + scoped resolve/escalate actions) — render the moderation queue without any edit form. Five Actions implement the state transitions: `FreezeChatAction`, `UnfreezeChatAction`, `ResolveChatFlagAction`, `MarkOffPlatformContactAttemptAction`, `EscalateChatFlagToAdminInboxAction`. Each Action wraps a `DB::transaction`, fires its domain event via `DB::afterCommit`, writes one `audit_logs` row, and refuses to UPDATE any content-bearing column on `chat_message_log` (only `flagged`, `flag_reason`, `redacted` may flip). All reasons are bilingual EN+AR (Constitution §IV). Escalation pushes into the existing Phase 6.1 `admin_inbox_items` table via the routing rule engine. Pest covers freeze happy/idempotent/422/403, unfreeze 409, flag detection across phone/email/link/innocent, the no-content-mutation invariant, escalation idempotency, and the absence of edit routes on `ChatMessageLogResource` and `ChatThreadResource`.

## Technical Context

**Language/Version**: PHP 8.3, Laravel 12 (per `02_Tech_Decisions.md §1`).
**Primary Dependencies**:
- `filament/filament` v3 (admin UI — already installed)
- `filament/spatie-laravel-translatable-plugin` (bilingual notification preview — already installed)
- `spatie/laravel-permission` (`chat_moderation.*` permissions)
- `bezhansalleh/filament-shield` (regenerate after new resources)
- Laravel Horizon / Redis queue (queue name `chat-moderation`) — already installed
- No new packages. `10_Package_List.md` unchanged.

**Storage**: MySQL 8 / MariaDB 11, utf8mb4 / utf8mb4_unicode_ci. Two locked tables receive their first migrations: `chat_message_log` and `chat_moderation_flags` (column shapes from `11_DB_Schema.md` lines 1131–1159). `chat_threads` is reused as-is; no schema changes. Append-only contract enforced on `chat_message_log` content fields (`sender_id`, `firestore_message_id`, `message_kind`, `detected_locale`) — no Action writes to them after insert.

**Testing**: Pest, with `tests/Feature/Modules/Communication/AdminChatModeration/*` and `tests/Unit/Modules/Communication/ChatModeration/*`. Groups: `communication`, `chat-moderation`, `admin`. Reuses the shared `RefreshDatabase` test trait and `Filament\Testing\TestsActions` helpers already present in the test suite.

**Target Platform**: Laravel API + Filament admin panel at `/admin`. No public customer/vendor endpoints in this feature (Firestore drives the chat itself).

**Project Type**: Backend modular monolith (single project).

**Performance Goals**:
- `DetectSuspiciousMessageJob` regex evaluation completes in **< 50 ms p95** on a single message.
- Restricted Chat index page loads in **< 800 ms p95** for 50k thread rows with all eager-loaded relations and unresolved-flag count.
- Freeze / Unfreeze / Resolve actions complete (transaction + audit + dispatch enqueue) in **< 300 ms p95**.

**Constraints**:
- Bilingual EN+AR mandatory for every admin reason input (Constitution §IV).
- Domain events fire only via `DB::afterCommit` (Constitution §VI).
- `chat_message_log` is append-only on content fields — UPDATE allowed only on `flagged`, `flag_reason`, `redacted`.
- Eastern Arabic digits (٠–٩) must be normalized before phone-regex match.
- Unfreeze refused when `booking_vendors.sub_status NOT IN ('pending','modified')` (Vendor Journey rule, `07_Vendor_Journey.md` line 198).
- Idempotency: re-running `FreezeChatAction` on a frozen thread, `ResolveChatFlagAction` on a resolved flag, or `EscalateChatFlagToAdminInboxAction` on an escalated flag must be no-ops without duplicate audit rows.

**Scale/Scope**:
- Phase 1 traffic estimate: 100–500 active chats/day across all bookings; the moderation queue therefore stays small enough that no partitioning or read-replica routing is needed.
- Audit logs grow with admin activity (~10s per day in Phase 1); fits in the existing `audit_logs` table partitioning plan.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle (from `.specify/memory/constitution.md`) | Status | Notes |
|---|---|---|
| I. Modular Monolith — Communication module owns these tables and Actions | ✅ Pass | All new code lives under `app/Modules/Communication/` |
| II. Three Product Types — `match($enum)` for branching | ✅ Pass | Index page filters by `product_type` via `booking_items` join; no per-type Action variants needed (chat is type-agnostic by design — moderation rules are identical across rental/sale/digital) |
| III. Money Discipline — integer minor units, `Brick\Money` | ⚪ N/A | No money in this feature |
| IV. Bilingual EN+AR mandatory | ✅ Pass | Every reason field (`*_en`, `*_ar`) validated; notification templates registered bilingually |
| V. Append-only ledger discipline | ✅ Pass | `chat_message_log` is INSERT-only on content; only `flagged`/`flag_reason`/`redacted` may UPDATE — codified in `ChatMessageLog` model via `$fillable` whitelist and an Action-layer assertion |
| VI. Domain events fire after commit | ✅ Pass | All five Actions use `DB::afterCommit(fn () => event(...))` |
| VII. `audit_logs` on every state transition + admin action | ✅ Pass | `WriteChatModerationAuditListener` writes one row per Freeze / Unfreeze / Resolve / Mark / Escalate; verified by Pest count delta assertions |
| VIII. Idempotency on payment-mutating endpoints | ⚪ N/A | No payments in this feature |
| IX. Filament resources in module's `Filament/Resources/` | ✅ Pass | `app/Modules/Communication/Filament/Resources/ChatThreadResource.php`, `ChatMessageLogResource.php`, `ChatModerationFlagResource.php` |
| X. No package not in `10_Package_List.md` | ✅ Pass | No new packages |
| XI. No Phase 2 features | ✅ Pass | ML-based detection deferred per Phase 8.2 cut-list (line 1726); regex-only here |
| XII. Phase alignment cited | ✅ Pass | Phase 8.2 — Restricted Chat, Compliance & Off-Platform Prevention (`09_Phasing_Plan.md` line 1681) |
| XIII. ADR for cross-cutting decisions | ✅ Pass | ADR-0014 to be authored before merge per Phase 8.2 plan |

No violations → **Complexity Tracking** section intentionally left empty.

## Project Structure

### Documentation (this feature)

```text
specs/036-admin-chat-moderation/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/
│   ├── freeze-thread.md
│   ├── unfreeze-thread.md
│   ├── resolve-flag.md
│   ├── mark-off-platform.md
│   ├── escalate-to-inbox.md
│   └── events.md
├── checklists/
│   └── requirements.md
└── tasks.md             # Phase 2 output (NOT created here)
```

### Source Code (repository root)

```text
app/Modules/Communication/
├── Domain/
│   ├── Enums/
│   │   ├── ChatFreezeCategory.php                       # NEW (off_platform_contact, policy_violation, harassment, other)
│   │   ├── ChatFlagType.php                             # NEW (phone, email, profanity, external_link, other)
│   │   ├── ChatFlagAction.php                           # NEW (redact, warn, block, none)
│   │   ├── ChatFlagResolution.php                       # NEW (upheld_redact, upheld_warn, upheld_block, dismissed_false_positive)
│   │   └── ChatMessageFlagReason.php                    # NEW (phone_pattern, email_pattern, external_link, manual, post_lock)
│   ├── Events/
│   │   ├── ChatThreadFrozen.php                         # NEW
│   │   ├── ChatThreadUnfrozen.php                       # NEW
│   │   ├── ChatMessageFlagged.php                       # NEW (fired by detection job)
│   │   ├── ChatModerationFlagResolved.php               # NEW
│   │   └── ChatFlagEscalatedToInbox.php                 # NEW
│   └── Models/
│       ├── ChatThread.php                               # EXTEND (add unresolvedFlagsCount relation, messages relation, scopes)
│       ├── ChatMessageLog.php                           # NEW (Eloquent model — fillable whitelist excludes content fields after insert)
│       └── ChatModerationFlag.php                       # NEW
├── Application/
│   ├── Actions/
│   │   ├── FreezeChatAction.php                         # NEW
│   │   ├── UnfreezeChatAction.php                       # NEW
│   │   ├── ResolveChatFlagAction.php                    # NEW
│   │   ├── MarkOffPlatformContactAttemptAction.php      # NEW
│   │   └── EscalateChatFlagToAdminInboxAction.php       # NEW
│   ├── DTOs/
│   │   ├── FreezeChatDTO.php                            # NEW (reason_en, reason_ar, category)
│   │   ├── UnfreezeChatDTO.php                          # NEW
│   │   ├── ResolveChatFlagDTO.php                       # NEW (decision, note_en, note_ar)
│   │   ├── MarkOffPlatformContactDTO.php                # NEW
│   │   └── EscalateChatFlagDTO.php                      # NEW (severity, summary_en, summary_ar)
│   ├── Listeners/
│   │   └── WriteChatModerationAuditListener.php         # NEW (single listener fanout-routed per event)
│   └── Jobs/
│       └── DetectSuspiciousMessageJob.php               # NEW (queued, idempotent per (message_log_id, flag_type))
├── Infrastructure/
│   └── Services/
│       ├── MessagePatternDetector.php                   # NEW (regex set + Eastern-Arabic digit normalization)
│       └── ChatModerationRoutingHelper.php              # NEW (bridges flag→admin_inbox via existing inbox routing rule engine)
├── Http/
│   ├── Controllers/Admin/
│   │   ├── FreezeChatController.php                     # NEW (POST /admin/chat-threads/{id}/freeze)
│   │   ├── UnfreezeChatController.php                   # NEW
│   │   ├── ResolveChatFlagController.php                # NEW
│   │   ├── MarkOffPlatformContactController.php        # NEW
│   │   └── EscalateChatFlagController.php               # NEW
│   ├── Requests/Admin/
│   │   ├── FreezeChatRequest.php                        # NEW (bilingual validation)
│   │   ├── UnfreezeChatRequest.php                      # NEW
│   │   ├── ResolveChatFlagRequest.php                   # NEW
│   │   ├── MarkOffPlatformContactRequest.php            # NEW
│   │   └── EscalateChatFlagRequest.php                  # NEW
│   └── Resources/
│       ├── ChatThreadResource.php                       # NEW (API resource for JSON response shape; @response PHPDoc)
│       ├── ChatMessageLogResource.php                   # NEW (API)
│       └── ChatModerationFlagResource.php               # NEW (API)
├── Filament/
│   └── Resources/
│       ├── ChatThreadResource.php                       # NEW (view-only — Pages\ListChatThreads, Pages\ViewChatThread)
│       ├── ChatThreadResource/
│       │   └── Pages/
│       │       ├── ListChatThreads.php                  # NEW
│       │       └── ViewChatThread.php                   # NEW (Infolist with Booking Context + Message View + Audit Timeline)
│       ├── ChatMessageLogResource.php                   # NEW (read-only list + view)
│       ├── ChatMessageLogResource/
│       │   └── Pages/
│       │       ├── ListChatMessageLogs.php              # NEW
│       │       └── ViewChatMessageLog.php               # NEW
│       ├── ChatModerationFlagResource.php               # NEW
│       └── ChatModerationFlagResource/
│           └── Pages/
│               ├── ListChatModerationFlags.php          # NEW
│               └── ViewChatModerationFlag.php           # NEW (with Resolve and Escalate row actions)
├── Routes/
│   └── admin.php                                        # EDIT — register the 5 admin POST routes
└── Database/
    ├── Migrations/
    │   ├── 2026_05_17_100001_create_chat_message_log_table.php       # NEW
    │   └── 2026_05_17_100002_create_chat_moderation_flags_table.php  # NEW
    ├── Factories/
    │   ├── ChatMessageLogFactory.php                                  # NEW
    │   └── ChatModerationFlagFactory.php                              # NEW
    └── Seeders/
        └── ChatModerationPermissionsSeeder.php                        # NEW (chat_moderation.* permissions)

app/Modules/Communication/Resources/lang/
├── en/chat_moderation.php                               # NEW (UI strings)
└── ar/chat_moderation.php                               # NEW

tests/
├── Feature/Modules/Communication/AdminChatModeration/
│   ├── FreezeChatTest.php                               # happy path, idempotency, 422 missing AR, 403 no permission
│   ├── UnfreezeChatTest.php                             # happy path, 409 outside review window, 403
│   ├── ResolveChatFlagTest.php                          # all 4 decisions, 409 already-resolved, no-content-mutation invariant
│   ├── MarkOffPlatformContactTest.php
│   ├── EscalateChatFlagTest.php                         # creates inbox item, idempotent on re-escalate
│   ├── DetectSuspiciousMessageJobTest.php               # phone (Latin + Arabic), email, link, innocent — idempotency
│   ├── ChatThreadResourceIndexTest.php                  # filters, sort, eager-load, permission gate
│   ├── ChatMessageLogResourceReadOnlyTest.php           # no create/edit routes; redacted body rendered as placeholder
│   ├── AuditTimelineTest.php                            # 4-event sequence, no edit/delete buttons rendered
│   └── NoChatContentMutationInvariantTest.php           # static assertion: Eloquent fillable whitelist forbids content fields
└── Unit/Modules/Communication/ChatModeration/
    ├── MessagePatternDetectorTest.php                   # exhaustive regex matrix (Egyptian carrier prefixes, dashed/spaced, Arabic digits)
    └── ChatFlagTypeEnumTest.php
```

**Structure Decision**: Single-project modular monolith. All new code lives under `app/Modules/Communication/` — chat threading and moderation belong squarely to the Communication module per `02_Tech_Decisions.md §1` and the Phase 8.2 table-touch list. Filament admin lives inside the same module per the existing per-module Resources discovery convention (already used by `AdminInboxResource`, `NotificationDispatchResource`, etc.).

## Complexity Tracking

> Constitution Check passes with no violations. Section intentionally empty.

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| — | — | — |
