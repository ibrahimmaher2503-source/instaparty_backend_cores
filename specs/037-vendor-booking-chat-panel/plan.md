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
- FR traceability: If the feature maps to existing PRD coverage → cite specific FR numbers from 01_PRD.md. If the feature is NEW or extends beyond the PRD → define local requirement numbers prefixed FR-EXT-NNN and add a "⚠️ BACKFILL NEEDED: add to 01_PRD.md" note. Never leave requirements untraced.
- Schema traceability: If using an existing table → cite its name from 11_DB_Schema.md. If this feature introduces NEW tables → list them explicitly with a "⚠️ NEW TABLE — not yet in 11_DB_Schema.md" marker.
- Phase alignment: If the feature belongs to an existing phase → cite the Phase ID from 09_Phasing_Plan.md. If the feature is new work not yet phased → propose a Phase ID extension (e.g., Phase 1.X) and add a "⚠️ PHASE BACKFILL NEEDED" note.
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

# Implementation Plan: Vendor Booking Chat Panel (RestrictedChatPanel)

**Branch**: `036-admin-chat-moderation` | **Date**: 2026-05-16 | **Spec**: [spec.md](spec.md)
**Phase**: 8.2 — Restricted Chat, Compliance & Off-Platform Prevention

---

## Summary

Add a `RestrictedChatPanel` Livewire component embedded in `VendorBookingDetailPage` and `VendorBookingDecisionPage`. The panel surfaces the restricted booking chat to the vendor: message history (from `chat_message_log`), a five-state panel indicator (Open / Frozen / Closed / SystemLocked / Placeholder), an admin-freeze banner when applicable, and a compose form available only during the review window. Server-side off-platform contact validation (`OffPlatformPatternDetector`) runs on every send; blocked messages create `chat_moderation_flags` rows and audit trail entries before the Firestore gateway is called.

No new DB tables. No new REST endpoints. All writes go through the `SendVendorChatMessageAction` which extends the existing `FirestoreChatGateway` contract.

---

## Technical Context

**Language/Version**: PHP 8.3+ / Laravel 12
**Primary Dependencies**: Filament v3 (Livewire 3 embedded), `spatie/laravel-permission`, `spatie/laravel-activitylog`, Redis (Cache facade for dedup TTL)
**Storage**: MySQL 8 (`chat_message_log`, `chat_moderation_flags`, `audit_logs`); Redis (10-second dedup keys); Firestore (via stub gateway — real integration Phase 2)
**Testing**: Pest with `pestphp/pest-plugin-laravel`; `RefreshDatabase` trait; Livewire test helpers (`Livewire::test()`)
**Target Platform**: Filament v3 Vendor Panel (`/vendor`) — server-rendered Livewire, desktop-first
**Project Type**: Modular monolith — `app/Modules/Communication/`
**Performance Goals**: Panel renders in < 3s cold; send round-trip < 5s (SC-001)
**Constraints**: No Phase 2 features (real Firestore writes, chat media); no new Composer packages; constitution §IV (EN+AR mandatory); constitution §V (append-only tables); constitution §VII (no business logic in models)
**Scale/Scope**: Per-booking vendor chat; initial load 50 messages; one component per booking-vendor pair

---

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Applies? | Status | Notes |
|---|---|---|---|
| I — Modular Monolith | ✅ | PASS | All new code lives in `app/Modules/Communication/`. Booking pages reference the component by class name but do NOT import Communication models. |
| II — Three Product Types | ✅ | PASS | This feature is cross-type — chat is the same for rental, sale, and digital bookings. No `match($enum)` branching required. |
| III — Money Discipline | N/A | — | No money columns introduced. |
| IV — Bilingual EN+AR | ✅ | PASS | Every label, banner, toast, and validation message has both EN and AR keys in `lang/{en,ar}/chat.php`. |
| V — Append-Only Tables | ✅ | PASS | `chat_message_log` is insert-only beyond `flagged`/`flag_reason`/`redacted`. `audit_logs` fully append-only. No UPDATE on content fields. |
| VI — Spec-Driven (ADR before code) | ✅ | PASS | ADR-0014 covers the chat compliance architecture; this spec adds an Internal Decision entry. No new ADR required. |
| VII — Test-First for Critical Paths | ✅ | PASS | 12 Pest test cases mandated (FR-EXT-037-060). |
| VIII — Idempotency | ✅ | PASS (simplified) | 10-second Redis dedup key for network-retry protection. Full `idempotency_keys` table reserved for payment endpoints. Deviation documented in ADR-0014. |
| IX — Domain Events `DB::afterCommit` | ✅ | PASS | Firestore gateway call and `ChatFlagged` event both fire via `DB::afterCommit()` — never inside the transaction. |
| X — Vendor Approval Two-Step Gate | ✅ | PASS | Panel mount aborts 403 on suspended `vendor_profiles.approval_status`. |
| XI — Document Storage | N/A | — | No file uploads. |

**Post-design re-check (Phase 1)**: All principles remain PASS after data-model and contract design.

---

## ADR Reference

**ADR-0014** (`docs/adr/ADR-0014-chat-compliance-admin-oversight.md`) — Status: Accepted (authored in spec 036 branch).

This spec adds the following **Internal Decisions** to ADR-0014 §6:

> **ID-037-1**: Vendor-side send path uses `OffPlatformPatternDetector` (shared service in `Communication/Application/Services/`) as the primary compliance gate. The admin moderation job (spec 036) uses the same service as a secondary sweep. `FirestoreChatGateway::sendMessage()` is added to the contract; the stub returns a fake ID. Firestore gateway call fires in `DB::afterCommit()` so a gateway failure does not orphan a `chat_message_log` row.
>
> **ID-037-2**: Short-lived Redis dedup (10s TTL) protects against Livewire double-submit and mobile-reconnect retries. The full `idempotency_keys` table pattern is reserved for payment-path endpoints per Constitution §VIII.

---

## Dependency Gate

Before implementation begins, verify:

- [ ] `specs/036-admin-chat-moderation/` migrations are merged and `chat_message_log` + `chat_moderation_flags` tables exist (or factories stubbed for tests).
- [ ] ADR-0014 exists at `docs/adr/ADR-0014-chat-compliance-admin-oversight.md` with status `Accepted`.
- [ ] `FirestoreChatGatewayStub` and `FirestoreChatGateway` contract are present (confirmed: exist on current branch).

---

## Project Structure

### Documentation (this feature)

```text
specs/037-vendor-booking-chat-panel/
├── plan.md              ← this file
├── research.md          ← Phase 0 (6 decisions resolved)
├── data-model.md        ← Phase 1 (models, enums, services, component blueprint, i18n keys)
├── contracts/
│   └── FirestoreChatGateway.php   ← amended interface (+sendMessage)
├── quickstart.md        ← layer-by-layer implementation guide
├── checklists/
│   └── requirements.md  ← all items pass
└── tasks.md             ← Phase 2 output (/speckit.tasks — NOT yet created)
```

### Source Code (Communication module)

```text
app/Modules/Communication/
├── Application/
│   ├── Actions/
│   │   └── SendVendorChatMessageAction.php          [NEW]
│   ├── DTOs/
│   │   └── SendVendorChatMessageDTO.php             [NEW]
│   └── Services/
│       ├── OffPlatformPatternDetector.php           [NEW — shared with spec 036]
│       ├── OffPlatformMatch.php                     [NEW — value object]
│       └── ChatPanelStateResolver.php               [NEW]
├── Domain/
│   ├── Contracts/
│   │   └── FirestoreChatGateway.php                 [AMEND — add sendMessage()]
│   ├── Enums/
│   │   ├── ChatPanelState.php                       [NEW]
│   │   ├── ChatFlagType.php                         [NEW — shared with spec 036]
│   │   └── ChatFlagAction.php                       [NEW — shared with spec 036]
│   └── Models/
│       ├── ChatThread.php                           [AMEND — add messages() HasMany]
│       ├── ChatMessageLog.php                       [NEW — from spec 036 migration]
│       └── ChatModerationFlag.php                   [NEW — from spec 036 migration]
├── Filament/
│   └── Vendor/
│       └── Components/
│           └── RestrictedChatPanel.php              [NEW — Livewire component]
├── Infrastructure/
│   └── Gateways/
│       └── FirestoreChatGatewayStub.php             [AMEND — add sendMessage()]
└── Resources/
    └── lang/
        ├── en/chat.php                              [AMEND — new keys]
        └── ar/chat.php                              [AMEND — new keys]
```

### Blade views

```text
resources/views/
└── vendor-portal/
    ├── components/
    │   └── restricted-chat-panel.blade.php          [NEW — Livewire view]
    └── pages/
        ├── vendor-booking-detail.blade.php           [AMEND — add @livewire embed]
        └── vendor-booking-decision.blade.php         [AMEND — add @livewire embed]
```

### Tests

```text
tests/
├── Feature/Modules/Communication/VendorRestrictedChat/
│   ├── SendCleanMessageTest.php                     [NEW]
│   ├── BlockedMessageTest.php                       [NEW]
│   ├── PanelStateTest.php                           [NEW]
│   └── AuthorizationTest.php                        [NEW]
└── Unit/Modules/Communication/
    └── OffPlatformPatternDetectorTest.php           [NEW]
```

---

## Implementation Order (dependency-first, 24 steps)

### Layer 0 — Prerequisite check

1. Verify spec 036 migrations exist; stub factories if absent.

### Layer 1 — Enums and value objects (no dependencies)

2. `ChatFlagType` enum
3. `ChatFlagAction` enum
4. `ChatPanelState` enum
5. `OffPlatformMatch` value object (readonly class)

### Layer 2 — Contract amendment

6. Amend `FirestoreChatGateway` — add `sendMessage(string $firestoreThreadId, string $senderUserId, string $body): string`
7. Amend `FirestoreChatGatewayStub` — implement `sendMessage()` returning `'stub_msg_'.Str::uuid()`

### Layer 3 — Models

8. `ChatMessageLog` model — relationships, casts, `timestamps()` override (no `updated_at`)
9. `ChatModerationFlag` model — relationships, casts
10. Amend `ChatThread` — add `messages()` HasMany relation to `ChatMessageLog`

### Layer 4 — Services

11. `OffPlatformPatternDetector` + `OffPlatformMatch` value object
12. `ChatPanelStateResolver` — 5-state precedence logic

### Layer 5 — DTO and Action

13. `SendVendorChatMessageDTO`
14. `SendVendorChatMessageAction` — execute() with ownership check, state guard, pattern detection, DB::transaction + DB::afterCommit gateway call

### Layer 6 — Livewire component

15. `RestrictedChatPanel` Livewire component class
16. `restricted-chat-panel.blade.php` Blade view

### Layer 7 — Translation keys (parallel with Layer 6)

17. `lang/en/chat.php` — add new keys (panel_title, status_*, freeze_banner_body, etc.)
18. `lang/ar/chat.php` — same keys in Arabic

### Layer 8 — Host page integration

19. Amend `vendor-booking-detail.blade.php` — add `@livewire(RestrictedChatPanel::class, ['bookingVendorPublicId' => $bookingVendor])`
20. Amend `vendor-booking-decision.blade.php` — same embed

### Layer 9 — Tests

21. `OffPlatformPatternDetectorTest` (unit — no DB, covers all 4 regex patterns + normalization)
22. `SendCleanMessageTest` (feature — happy path, Livewire::test, verifies gateway called once)
23. `BlockedMessageTest` (feature — phone Latin, phone Eastern Arabic, email, external link; verifies zero gateway calls)
24. `PanelStateTest` (feature — frozen banner visible, compose form absent when frozen/closed/system_locked)
25. `AuthorizationTest` (feature — wrong vendor 403, unauthenticated redirect, suspended vendor 403)

---

## Cut-list (if behind schedule)

| Item | Cut decision |
|---|---|
| `loadEarlierMessages()` pagination | Defer — show only 50 messages, no "load more" |
| Eastern Arabic digit normalization | Keep — compliance critical |
| Redis dedup 10-second TTL | Keep — prevents visible duplicates |
| AR translation keys | Keep — Constitution §IV non-negotiable |
| Admin-freeze banner | Keep — vendor experience is broken without it |

---

## Exit Criteria

- [ ] `./vendor/bin/pest tests/Feature/Modules/Communication/VendorRestrictedChat/` — all green
- [ ] `./vendor/bin/pest tests/Unit/Modules/Communication/OffPlatformPatternDetectorTest.php` — all green
- [ ] Panel renders in EN and AR locale (manual QA on `VendorBookingDetailPage`)
- [ ] Blocked-message test asserts zero Firestore gateway calls
- [ ] Wrong-vendor test asserts 403 on every data path
- [ ] `./vendor/bin/pint` — zero violations
- [ ] `./vendor/bin/phpstan analyse` — level 8, zero errors
