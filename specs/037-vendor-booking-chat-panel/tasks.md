# Tasks: Vendor Booking Chat Panel (RestrictedChatPanel)

**Input**: Design documents from `specs/037-vendor-booking-chat-panel/`
**Branch**: `036-admin-chat-moderation`
**Phase**: 8.2 — Restricted Chat, Compliance & Off-Platform Prevention
**Tests**: INCLUDED — mandated by FR-EXT-037-060

**User Stories** (all P1):
- US1 — Vendor sends a clean message during the review window
- US2 — Vendor message blocked for off-platform contact pattern
- US3 — Chat panel shows admin-freeze banner when thread is frozen
- US4 — Chat panel is closed after the review window ends
- US5 — Wrong vendor cannot access another vendor's chat

---

## Format: `[ID] [P?] [Story] Description with file path`

- **[P]**: Parallelisable (different file, no incomplete dependencies)
- **[Story]**: User story label (US1–US5)
- All Pest tests use `->group('vendor-chat')`

---

## Phase 1: Setup — Prerequisite Verification

**Purpose**: Gate-check that spec 036 infrastructure exists before writing any code.

- [X] T001 Verify `chat_message_log` and `chat_moderation_flags` migrations are present in `app/Modules/Communication/Database/Migrations/`. If absent, create stub migrations matching the column shapes in `specs/037-vendor-booking-chat-panel/data-model.md` with filenames `2026_05_16_100005_create_chat_message_log_table.php` and `2026_05_16_100006_create_chat_moderation_flags_table.php`.
- [X] T002 Verify `docs/adr/ADR-0014-chat-compliance-admin-oversight.md` exists with status `Accepted`. Append Internal Decision entries **ID-037-1** (shared `OffPlatformPatternDetector`, `DB::afterCommit` gateway call pattern) and **ID-037-2** (10s Redis dedup deviation from full `idempotency_keys` table) to the ADR's §6 section as documented in `specs/037-vendor-booking-chat-panel/plan.md`.

**Checkpoint**: Migrations confirmed present; ADR updated.

---

## Phase 2: Foundational — Shared Infrastructure

**Purpose**: All enums, models, services, DTO, contract amendments, Action, and translation keys that EVERY user story depends on. No user story implementation can begin until this phase is complete.

**⚠️ CRITICAL**: Complete all Phase 2 tasks before starting Phase 3.

### Enums and value objects (no dependencies — parallelisable)

- [X] T003 [P] Create `ChatFlagType` backed string enum with cases `Phone = 'phone'`, `Email = 'email'`, `ExternalLink = 'external_link'`, `Profanity = 'profanity'`, `Other = 'other'` in `app/Modules/Communication/Domain/Enums/ChatFlagType.php`
- [X] T004 [P] Create `ChatFlagAction` backed string enum with cases `Block = 'block'`, `Warn = 'warn'`, `Redact = 'redact'`, `None = 'none'` in `app/Modules/Communication/Domain/Enums/ChatFlagAction.php`
- [X] T005 [P] Create `ChatPanelState` backed string enum with cases `Placeholder = 'placeholder'`, `Open = 'open'`, `Frozen = 'frozen'`, `Closed = 'closed'`, `SystemLocked = 'system_locked'` in `app/Modules/Communication/Domain/Enums/ChatPanelState.php`
- [X] T006 [P] Create `OffPlatformMatch` final readonly class — superseded; spec 036's `MatchedPattern` at `Infrastructure/Services/MatchedPattern.php` already serves this role.

### Contract and gateway amendments (T008 depends on T007)

- [X] T007 Amend `FirestoreChatGateway` interface — add `sendMessage(string $firestoreThreadId, string $senderUserId, string $body): string` as documented in `specs/037-vendor-booking-chat-panel/contracts/FirestoreChatGateway.php` to `app/Modules/Communication/Domain/Contracts/FirestoreChatGateway.php`
- [X] T008 Implement `sendMessage()` in `app/Modules/Communication/Infrastructure/Gateways/FirestoreChatGatewayStub.php` — log the call via `Log::info('FirestoreChatGateway::sendMessage', [...])` and return `'stub_msg_' . Str::uuid()`

### Models (depend on T001 — migrations must exist; T009 and T010 are parallelisable)

- [X] T009 [P] Create `ChatMessageLog` Eloquent model in `app/Modules/Communication/Domain/Models/ChatMessageLog.php` — existed from spec 036 with correct shape.
- [X] T010 [P] Create `ChatModerationFlag` Eloquent model in `app/Modules/Communication/Domain/Models/ChatModerationFlag.php` — existed from spec 036 with correct shape.
- [X] T011 Amend `app/Modules/Communication/Domain/Models/ChatThread.php` — `messages(): HasMany` already present from spec 036.

### Services (T012 and T013 parallelisable; depend on T003–T006)

- [X] T012 [P] Create `OffPlatformPatternDetector` — superseded; spec 036's `MessagePatternDetector` at `Infrastructure/Services/MessagePatternDetector.php` already serves this role with identical regex patterns.
- [X] T013 [P] Create `ChatPanelStateResolver` service in `app/Modules/Communication/Application/Services/ChatPanelStateResolver.php` — created with 7-step precedence, signature `resolve(?ChatThread $thread, Booking $booking): ChatPanelState`.

### DTO and Action (T015 depends on T012, T013, T014, T007, T009)

- [X] T014 Create `SendVendorChatMessageDTO` final readonly class — created with simplified constructor `(string $body)` in `app/Modules/Communication/Application/DTOs/SendVendorChatMessageDTO.php`
- [X] T015 Create `SendVendorChatMessageAction` in `app/Modules/Communication/Application/Actions/SendVendorChatMessageAction.php` — full algorithm implemented: ownership check, state guard via ChatPanelStateResolver, 10s Redis dedup, MessagePatternDetector, DB::transaction with ChatMessageLog + ChatModerationFlag creation, audit_logs row, DB::afterCommit gateway call (clean) or ChatFlagged+ChatMessageFlagged events (blocked).

### Translation keys (parallelisable with each other and with T018)

- [X] T016 [P] Created `app/Modules/Communication/Resources/lang/en/chat.php` with 23 keys.
- [X] T017 [P] Created `app/Modules/Communication/Resources/lang/ar/chat.php` with 23 Arabic keys.

### Foundational unit test (depends on T012; parallelisable with T016, T017)

- [X] T018 [P] `OffPlatformPatternDetectorTest` — superseded; spec 036's `MessagePatternDetectorTest` at `tests/Unit/Modules/Communication/ChatModeration/MessagePatternDetectorTest.php` already covers all these cases.

**Foundational Checkpoint**: `./vendor/bin/pest tests/Unit/Modules/Communication/OffPlatformPatternDetectorTest.php` — all pass before Phase 3.

---

## Phase 3: User Story 1 — Vendor Sends a Clean Message (P1) 🎯 MVP

**Goal**: Vendor with an open thread can compose and send a clean message from both host pages. The send writes to `chat_message_log`, calls the Firestore gateway stub once, and writes an audit row. No moderation flag is created.

**Independent Test**: Seed `open` chat_thread (`frozen_at=null`) + pending `booking_vendor`. Log in as the owning vendor. `Livewire::test(RestrictedChatPanel::class, ['bookingVendorPublicId' => $bv->public_id])->call('sendMessage')` with clean body. Assert: one `chat_message_log` row with `flagged=false`, zero `chat_moderation_flags` rows, gateway spy called once, one `audit_logs` row with `action='chat.message_sent'`.

### Implementation

- [X] T019 [US1] Created `RestrictedChatPanel` Livewire component in `app/Modules/Communication/Filament/Vendor/Components/RestrictedChatPanel.php` — registered as `communication.vendor.restricted-chat-panel` in CommunicationServiceProvider.
- [X] T020 [US1] Created `resources/views/vendor-portal/components/restricted-chat-panel.blade.php` + `chat-messages-list.blade.php` partial.
- [X] T021 [P] [US1] Amended `resources/views/vendor-portal/pages/vendor-booking-detail.blade.php` — added `@livewire('communication.vendor.restricted-chat-panel', ...)`.
- [X] T022 [P] [US1] Amended `resources/views/vendor-portal/pages/vendor-booking-decision.blade.php` — added `@livewire('communication.vendor.restricted-chat-panel', ...)`.

### Tests for User Story 1

- [X] T023 [US1] Created `tests/Feature/Modules/Communication/VendorRestrictedChat/SendCleanMessageTest.php` — 6 tests covering happy path, audit row, duplicate dedup, ownership, and frozen-thread guards.

**US1 Checkpoint**: `./vendor/bin/pest tests/Feature/Modules/Communication/VendorRestrictedChat/SendCleanMessageTest.php` — all green.

---

## Phase 4: User Story 2 — Vendor Message Blocked for Off-Platform Contact (P1)

**Goal**: Phone numbers, emails, and external links are blocked server-side. No Firestore write occurs. A `chat_moderation_flags` row + audit log row are created. Vendor sees bilingual blocked toast. Eastern Arabic digits pass normalization.

**Independent Test**: Seed open thread + pending `booking_vendor`. Call `sendMessage` with `"01012345678"`. Assert: zero gateway calls, `chat_message_log.flagged = true`, one `chat_moderation_flags` row (`flag_type='phone'`, `action_taken='block'`), one `audit_logs` row (`action='chat.message_blocked'`).

### Tests for User Story 2

- [X] T024 [US2] Created `tests/Feature/Modules/Communication/VendorRestrictedChat/BlockedMessageTest.php` — 8 tests covering phone/email/external-link patterns, ChatFlagged/ChatMessageFlagged events, audit row, and gateway NOT called for blocked messages.

**US2 Checkpoint**: `./vendor/bin/pest tests/Feature/Modules/Communication/VendorRestrictedChat/BlockedMessageTest.php` — all green.

---

## Phase 5: User Story 3 — Admin-Freeze Banner (P1)

**Goal**: When `chat_threads.frozen_at IS NOT NULL`, the panel renders in Frozen state: freeze banner visible in both locales, compose form absent from DOM, server-side send rejected.

**Independent Test**: Seed thread with `frozen_at = now()`, `status = 'locked'`. Mount `RestrictedChatPanel` as the owning vendor. Assert compose form absent from rendered HTML, freeze banner contains `__('communication::chat.freeze_banner_body')`, frozen_at timestamp visible, direct `sendMessage()` Livewire call results in error notification and zero DB writes.

### Implementation

- [X] T025 [US3] Verified: blade view (T020) hides compose form for all non-Open states and renders freeze banner only when state is Frozen — confirmed in implementation.

### Tests for User Story 3

- [ ] T026 [US3] Create `tests/Feature/Modules/Communication/VendorRestrictedChat/PanelStateTest.php` with `->group('vendor-chat')` covering frozen cases: (1) thread with `frozen_at IS NOT NULL` → `Livewire::test(RestrictedChatPanel)` → `assertSee` `__('communication::chat.freeze_banner_body')`; (2) same with AR locale → assertSee the Arabic freeze banner text; (3) compose form absent when frozen (`assertDontSee` the `<form wire:submit="sendMessage">` element); (4) `frozen_at` timestamp rendered in the banner; (5) system-locked thread (`status='locked'`, `frozen_at=null`) → `assertDontSee` freeze banner text, assertSee `__('communication::chat.closed_label_system_locked')`; (6) direct `sendMessage()` call on frozen thread → dispatches danger notification, zero `chat_message_log` rows written

**US3 Checkpoint**: Frozen-state assertions in `PanelStateTest.php` all pass.

---

## Phase 6: User Story 4 — Chat Closed After Review Window (P1)

**Goal**: When `booking_vendors.sub_status` is not `pending`/`modified`, or the booking lifecycle is `cancelled`/`completed`, or the thread `status = 'closed'`, the panel is read-only — no compose form, bilingual "chat closed" label.

**Independent Test**: Seed `open` thread + `booking_vendor.sub_status = 'accepted'`. Mount panel. Assert compose form absent, `__('communication::chat.closed_label_closed')` visible, direct `sendMessage()` call returns error.

### Tests for User Story 4

- [ ] T027 [US4] Add to `tests/Feature/Modules/Communication/VendorRestrictedChat/PanelStateTest.php` closed-state cases: (1) `sub_status = 'accepted'` → compose form absent, `closed_label_closed` visible; (2) `sub_status = 'rejected'` → same result; (3) `bookings.lifecycle_status = 'cancelled'` → closed label visible, compose absent; (4) `chat_threads.status = 'closed'` → read-only regardless of sub_status; (5) thread placeholder (`chat_thread` row does not exist) → `__('communication::chat.closed_label_placeholder')` shown; (6) direct `sendMessage()` call on any closed state → guard error dispatched, zero `chat_message_log` rows written

**US4 Checkpoint**: All assertions in `PanelStateTest.php` (frozen + closed + placeholder) pass.

---

## Phase 7: User Story 5 — Authorization Guards (P1)

**Goal**: Vendor B cannot access vendor A's chat via any path — page mount, Livewire action, or direct action call all return 403. Unauthenticated users redirect to login. Suspended vendors are refused at mount.

**Independent Test**: Seed booking_vendor A (vendor 1) + chat thread. Authenticate as vendor 2. `Livewire::actingAs(vendor2)->test(RestrictedChatPanel, ['bookingVendorPublicId' => vendor1BvPublicId])` → throws 403. Direct `SendVendorChatMessageAction::execute($vendor1Thread, $vendor2Profile, $dto)` → 403, zero DB writes.

### Tests for User Story 5

- [ ] T028 [US5] Create `tests/Feature/Modules/Communication/VendorRestrictedChat/AuthorizationTest.php` with `->group('vendor-chat')` covering: (1) wrong vendor mounts panel → 403 thrown; (2) wrong vendor calls `sendMessage()` Livewire action with vendor 1's thread_id → 403, zero `chat_message_log` rows; (3) unauthenticated mount → redirects to vendor login (assert redirect, no data rendered); (4) suspended vendor (`approval_status = 'suspended'`) → 403 at mount, no chat data loaded; (5) vendor without a `VendorProfile` record → 403 at mount; (6) authenticated vendor accessing their OWN booking → component mounts without exception

**US5 Checkpoint**: `./vendor/bin/pest tests/Feature/Modules/Communication/VendorRestrictedChat/AuthorizationTest.php` — all green.

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Code quality, full integration test run, manual QA sign-off, and checklist completion.

- [ ] T029 [P] Run `./vendor/bin/pint app/Modules/Communication/ resources/views/vendor-portal/components/restricted-chat-panel.blade.php` — fix all style violations in new and amended files
- [ ] T030 [P] Run `./vendor/bin/phpstan analyse app/Modules/Communication/ --level=8` — fix all type errors in new classes (`ChatMessageLog`, `ChatModerationFlag`, `OffPlatformPatternDetector`, `ChatPanelStateResolver`, `SendVendorChatMessageAction`, `RestrictedChatPanel`)
- [ ] T031 Run full vendor-chat test group: `./vendor/bin/pest --group=vendor-chat` — confirm all tests in `SendCleanMessageTest`, `BlockedMessageTest`, `PanelStateTest`, `AuthorizationTest`, and `OffPlatformPatternDetectorTest` pass with zero failures
- [ ] T032 [P] Manual QA — EN locale: start `php artisan serve`, open `/vendor/booking-decisions/{pendingBvPublicId}`; verify: panel renders at bottom of page, "Open" status badge visible, compose form visible, send clean message → success toast + message in list, send `"01012345678"` → blocked toast, no message in list
- [ ] T033 [P] Manual QA — AR locale: switch to Arabic via language switcher; open both `/vendor/booking-detail/{id}` and `/vendor/booking-decisions/{id}`; verify all panel text renders in Arabic, RTL layout intact, no raw `communication::chat.*` key strings visible
- [ ] T034 Check for regex duplication: grep `app/Modules/Communication/` for any inline phone/email/link regex patterns outside of `OffPlatformPatternDetector.php`. If spec 036's `ModerateIncomingChatMessageJob` already exists with inline patterns, refactor it to call `OffPlatformPatternDetector::detect()` instead.
- [ ] T035 [P] Update `specs/037-vendor-booking-chat-panel/checklists/requirements.md` — mark all implementation exit criteria from `plan.md` complete; note any cut-list items deferred.

---

## Dependencies & Execution Order

### Phase Dependencies

```
Phase 1 (Setup — prereqs)
    ↓
Phase 2 (Foundational — enums, models, services, action, i18n)
    ↓
Phase 3 (US1 — component + blade + host pages + clean-send test)
    ↓
Phase 4 (US2 — blocked message test)   ← depends on US1 component
Phase 5 (US3 — freeze banner test)     ← depends on US1 component
    ↓
Phase 6 (US4 — closed state — appends to PanelStateTest from Phase 5)
    ↓
Phase 7 (US5 — authorization test)
    ↓
Phase 8 (Polish)
```

### User Story Dependencies

| Story | Depends on Phase 2? | Depends on US1 component (T019)? |
|---|---|---|
| US1 (clean send) | Yes — action, models | No — builds it |
| US2 (blocked) | Yes — action | Yes — Livewire::test uses it |
| US3 (freeze banner) | Yes — ChatPanelState | Yes — Livewire::test uses it |
| US4 (closed state) | Yes — resolver | Yes — appends to PanelStateTest |
| US5 (authorization) | Yes — ownership check | Yes — Livewire::test uses it |

### Within Phase 2

```
Parallel group A (no deps):   T003, T004, T005, T006, T016, T017, T018
Parallel group B (after T001): T009, T010, T011
Sequential:                    T007 → T008
Sequential:                    group A + group B → T012, T013 (parallel) → T014 → T015
```

---

## Parallel Opportunities

```bash
# Phase 2 — launch enums + translations + unit test simultaneously:
[T003] ChatFlagType enum
[T004] ChatFlagAction enum
[T005] ChatPanelState enum
[T006] OffPlatformMatch value object
[T016] EN translation keys
[T017] AR translation keys
[T018] OffPlatformPatternDetectorTest

# Phase 3 — host page embeds (after T019 + T020):
[T021] vendor-booking-detail.blade.php
[T022] vendor-booking-decision.blade.php

# Phase 8 — code quality and QA (after T031):
[T029] Pint
[T030] PHPStan
[T032] Manual QA EN
[T033] Manual QA AR
[T035] Checklist update
```

---

## Implementation Strategy

### MVP (US1 only)

1. Phase 1 → Phase 2 → Phase 3
2. `./vendor/bin/pest tests/Feature/Modules/Communication/VendorRestrictedChat/SendCleanMessageTest.php`
3. Manual QA: open booking detail, send one clean message in EN + AR

At this point vendors can chat during the review window. The Action already guards blocked/frozen/closed states — they just don't have dedicated test coverage yet.

### Full Delivery (all 5 stories)

1. Phase 1 → 2 → 3 → 4 → 5 → 6 → 7 → 8
2. Each phase has its own checkpoint test run
3. Final sign-off: `./vendor/bin/pest --group=vendor-chat` (all green) + manual QA

---

## Task Count Summary

| Phase | Tasks | Notes |
|---|---|---|
| Phase 1 — Setup | 2 | T001–T002 |
| Phase 2 — Foundational | 16 | T003–T018 |
| Phase 3 — US1 Clean Send | 5 | T019–T023 |
| Phase 4 — US2 Blocked | 1 | T024 |
| Phase 5 — US3 Freeze Banner | 2 | T025–T026 |
| Phase 6 — US4 Closed State | 1 | T027 |
| Phase 7 — US5 Authorization | 1 | T028 |
| Phase 8 — Polish | 7 | T029–T035 |
| **Total** | **35** | |

---

## Notes

- All tests use `->group('vendor-chat')` → run with `./vendor/bin/pest --group=vendor-chat`
- `SendVendorChatMessageAction` NEVER UPDATEs content fields on `chat_message_log` (`sender_id`, `firestore_message_id`, `message_kind`, `detected_locale`) — append-only per Constitution §V
- Firestore gateway call fires in `DB::afterCommit()` — NEVER inside the transaction
- `OffPlatformPatternDetector` is the ONLY source of regex patterns — zero inline duplicates allowed
- Compose form renders ONLY when `$panelState === ChatPanelState::Open`
- Ownership check runs in BOTH `mount()` AND every Livewire action call (FR-EXT-037-031)
