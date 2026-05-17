---
description: "Task list for Admin Restricted Chat Moderation UI implementation"
---

# Tasks: Admin Restricted Chat Moderation UI

**Input**: Design documents from `/specs/036-admin-chat-moderation/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md
**Tests**: Pest tests are required per spec FR-EXT-036-020 and Constitution §VII. Test tasks are included for each user story.
**Phase alignment**: Phase 8.2 — Restricted Chat, Compliance & Off-Platform Prevention (`docs/specs/09_Phasing_Plan.md` line 1681).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: Maps to user stories from spec.md (US1–US7)
- All paths are absolute under repo root `C:\instaparty_backend_cores\`

## Path Conventions

- Module source: `app/Modules/Communication/`
- Tests: `tests/Feature/Modules/Communication/AdminChatModeration/`, `tests/Unit/Modules/Communication/ChatModeration/`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Locked-schema migrations, enums, DTOs, permissions, language scaffolding — must land before any user story.

- [ ] T001 Author ADR-0014 at `docs/adr/ADR-0014-chat-compliance-admin-oversight.md` covering: three-layer append-only enforcement on `chat_message_log`, curated regex set + Eastern-Arabic digit normalization, decision to keep `chat_threads.status` ENUM unchanged (use `frozen_at` as admin-freeze signal), Phase 8.2 cut-list (no ML).
- [ ] T002 Add ADR-0014 entry to `CLAUDE.md` ADR list under "Current ADRs".
- [ ] T003 [P] Create migration `app/Modules/Communication/Database/Migrations/2026_05_17_100001_create_chat_message_log_table.php` matching data-model.md §Migration 1 (no `updated_at`; UNIQUE `(chat_thread_id, firestore_message_id)`; indexes per spec).
- [ ] T004 [P] Create migration `app/Modules/Communication/Database/Migrations/2026_05_17_100002_create_chat_moderation_flags_table.php` matching data-model.md §Migration 2 (UNIQUE `(chat_message_log_id, flag_type)`; index `(flag_type, reviewed_at)`).
- [ ] T005 [P] Create enum `app/Modules/Communication/Domain/Enums/ChatFreezeCategory.php` per data-model.md.
- [ ] T006 [P] Create enum `app/Modules/Communication/Domain/Enums/ChatFlagType.php` per data-model.md.
- [ ] T007 [P] Create enum `app/Modules/Communication/Domain/Enums/ChatFlagAction.php` per data-model.md.
- [ ] T008 [P] Create enum `app/Modules/Communication/Domain/Enums/ChatFlagResolution.php` per data-model.md (includes `actionTaken()` map method).
- [ ] T009 [P] Create enum `app/Modules/Communication/Domain/Enums/ChatMessageFlagReason.php` per data-model.md.
- [ ] T010 [P] Create seeder `app/Modules/Communication/Database/Seeders/ChatModerationPermissionsSeeder.php` that idempotently creates the six `chat_moderation.*` permissions (view, freeze, unfreeze, resolve_flag, mark_off_platform, escalate) and assigns them to the `admin` role.
- [ ] T011 Register `ChatModerationPermissionsSeeder` in `database/seeders/DatabaseSeeder.php` (or the existing module seeder bootstrap) so `php artisan migrate --seed` activates it.
- [ ] T012 [P] Create language file `app/Modules/Communication/Resources/lang/en/chat_moderation.php` with UI strings for index columns, freeze/unfreeze/resolve modal labels, error messages, audit timeline labels, escalation title.
- [ ] T013 [P] Create language file `app/Modules/Communication/Resources/lang/ar/chat_moderation.php` mirroring T012 in Arabic.
- [ ] T014 Run `php artisan migrate` and `php artisan db:seed --class=ChatModerationPermissionsSeeder` against the dev database to confirm both migrations apply cleanly and permissions seed without error.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Models, DTOs, events, listener, infrastructure services — every user story consumes these.

**⚠️ CRITICAL**: No user story phase may begin until this phase is complete.

- [ ] T015 [P] Create Eloquent model `app/Modules/Communication/Domain/Models/ChatMessageLog.php` per data-model.md §`ChatMessageLog`. Include the `booted()` UPDATE guard that throws `\LogicException` if any column other than `flagged`/`flag_reason`/`redacted` is dirty.
- [ ] T016 [P] Create Eloquent model `app/Modules/Communication/Domain/Models/ChatModerationFlag.php` per data-model.md §`ChatModerationFlag` (casts `flag_type` and `action_taken` to enums; relations to `messageLog`, `reviewer`, `thread` HasOneThrough).
- [ ] T017 Extend `app/Modules/Communication/Domain/Models/ChatThread.php` with relations (`messages`, `unresolvedFlags`, `bookingVendor`) and scopes (`frozen`, `withOpenFlags`) per data-model.md.
- [ ] T018 [P] Create DTO `app/Modules/Communication/Application/DTOs/FreezeChatDTO.php` (`reasonEn`, `reasonAr`, `category: ChatFreezeCategory`).
- [ ] T019 [P] Create DTO `app/Modules/Communication/Application/DTOs/UnfreezeChatDTO.php` (`reasonEn`, `reasonAr`).
- [ ] T020 [P] Create DTO `app/Modules/Communication/Application/DTOs/ResolveChatFlagDTO.php` (`decision: ChatFlagResolution`, `noteEn`, `noteAr`).
- [ ] T021 [P] Create DTO `app/Modules/Communication/Application/DTOs/MarkOffPlatformContactDTO.php` (`chatMessageLogId`, `flagType`, `reasonEn`, `reasonAr`).
- [ ] T022 [P] Create DTO `app/Modules/Communication/Application/DTOs/EscalateChatFlagDTO.php` (`chatModerationFlagId`, `severity`, `summaryEn`, `summaryAr`).
- [ ] T023 [P] Create event `app/Modules/Communication/Domain/Events/ChatThreadFrozen.php` implementing `AuditableEvent` per contracts/events.md.
- [ ] T024 [P] Create event `app/Modules/Communication/Domain/Events/ChatThreadUnfrozen.php` per contracts/events.md.
- [ ] T025 [P] Create event `app/Modules/Communication/Domain/Events/ChatMessageFlagged.php` per contracts/events.md (system actor — `actor()` returns null).
- [ ] T026 [P] Create event `app/Modules/Communication/Domain/Events/ChatModerationFlagResolved.php` per contracts/events.md.
- [ ] T027 [P] Create event `app/Modules/Communication/Domain/Events/OffPlatformContactMarked.php` per contracts/events.md.
- [ ] T028 [P] Create event `app/Modules/Communication/Domain/Events/ChatFlagEscalatedToInbox.php` per contracts/events.md.
- [ ] T029 Create custom exceptions in `app/Modules/Communication/Domain/Exceptions/`: `ChatThreadUnfreezeForbidden.php` (renders 409 with bilingual body) and `ChatFlagAlreadyResolved.php` (renders 409). Wire bilingual `render()` returning ApiResponse envelope.
- [ ] T030 Create listener `app/Modules/Communication/Application/Listeners/WriteChatModerationAuditListener.php`. Single listener subscribed to all six events; reads `auditable()`, `actor()`, `action()`, `changes()` from the event and INSERTs one `audit_logs` row.
- [ ] T031 [P] Create `app/Modules/Communication/Infrastructure/Services/MessagePatternDetector.php` with public `detect(string $body): array` returning `MatchedPattern[]`. Implementation: normalize Eastern Arabic digits (٠–٩ → 0–9), strip ZWNJ/ZWJ/NBSP, collapse whitespace, then apply the four regex patterns from research.md §R4. Return distinct `MatchedPattern` value objects keyed by `flag_type` to satisfy `(message_log_id, flag_type)` uniqueness.
- [ ] T032 [P] Create `app/Modules/Communication/Infrastructure/Services/ChatModerationRoutingHelper.php` that wraps the existing `InboxRoutingRuleEngine` to resolve an admin assignee given a `ChatModerationFlag`.
- [ ] T033 Edit `app/Modules/Communication/Providers/CommunicationServiceProvider.php` to register: the six event-listener bindings from contracts/events.md, the `WriteChatModerationAuditListener` subscription, and a model binding for `ChatMessageLog` and `ChatModerationFlag` (route key = `public_id` once ULID column is added in a future migration, or `id` for now).
- [ ] T034 Add the `chat-moderation` queue to `config/horizon.php` (or `config/queue.php`) so the new detection job has a dedicated supervisor. Document in quickstart.md step 5 alignment.
- [ ] T035 [P] Create model factory `app/Modules/Communication/Database/Factories/ChatMessageLogFactory.php` for Pest seeding.
- [ ] T036 [P] Create model factory `app/Modules/Communication/Database/Factories/ChatModerationFlagFactory.php`.

**Checkpoint**: Foundation ready — user story phases can now proceed in parallel.

---

## Phase 3: User Story 1 — Admin Monitors Restricted Chat Threads From a Single Filament Page (Priority: P1) 🎯 MVP

**Goal**: Read-only Filament index of all `chat_threads` with booking ref / customer / vendor / status pill / flag count / last-message-at / frozen by/at, filterable by chat status, open-flag presence, product type, and date range.

**Independent Test**: Seed 10 mixed-status threads → log in as admin with `chat_moderation.view` → confirm rows render with correct columns, sort frozen-first, filter by product type, and clicking a row opens a view-only Infolist (no Edit form).

### Tests for User Story 1 (Pest)

- [ ] T037 [P] [US1] Write `tests/Feature/Modules/Communication/AdminChatModeration/ChatThreadResourceIndexTest.php` covering: 10-row render, sort by `frozen_at DESC NULLS LAST, last_message_at DESC`, product-type filter narrows results, "has open flags" filter hides healthy threads, eager-load count is constant (assert `DB::getQueryLog()` count is bounded).
- [ ] T038 [P] [US1] Write `tests/Feature/Modules/Communication/AdminChatModeration/ChatThreadResourcePermissionTest.php` asserting an admin without `chat_moderation.view` receives 403 and the resource is hidden from navigation.

### Implementation for User Story 1

- [ ] T039 [US1] Create Filament resource `app/Modules/Communication/Filament/Resources/ChatThreadResource.php` (view-only — no `CreateAction`, no `EditAction`, no `DeleteAction`). Navigation group "Communication", label "Restricted Chat", permission gate via Shield + `chat_moderation.view`.
- [ ] T040 [US1] Create page `app/Modules/Communication/Filament/Resources/ChatThreadResource/Pages/ListChatThreads.php`. Define table columns: booking reference (clickable to `BookingResource`), customer name, vendor display name, status pill (with derived "Frozen" pill when `frozen_at IS NOT NULL`), open-flag count badge, last-message-at relative, frozen-by, frozen-at. Eager-load `customer`, `vendorProfile.user`, `booking`, `frozenByUser`, withCount `unresolvedFlags`.
- [ ] T041 [US1] Implement filters on `ListChatThreads`: chat status (`SelectFilter`), open-flag presence (`TernaryFilter`), product type via booking_items join (`SelectFilter` with `ProductType::class` options), date range on `last_message_at` (`Filter` with two `DatePicker`s).
- [ ] T042 [US1] Add default sort to `ListChatThreads`: `frozen_at DESC NULLS LAST, last_message_at DESC` (use `->modifyQueryUsing` if Filament default sort can't express null-handling).
- [ ] T043 [US1] Run `php artisan shield:generate --all` to materialize `view_any_chat_thread` / `view_chat_thread` permissions; assign to `admin` role via the existing role seeder. Run T037 and T038 — they must pass before proceeding.

**Checkpoint**: US1 fully functional. Admin can see and filter every restricted chat thread on a single page.

---

## Phase 4: User Story 2 — Admin Freezes a Thread Suspected of Off-Platform Coordination (Priority: P1)

**Goal**: Bilingual-reason + category freeze action that flips `chat_threads.status='locked'`, sets `frozen_at`/`frozen_by`, writes one audit row, and dispatches bilingual notifications. Idempotent on already-frozen threads.

**Independent Test**: Seed an open thread → call `FreezeChatAction` with bilingual reason → assert state flips, audit row written, two notifications enqueued, re-freeze produces no duplicate audit.

### Tests for User Story 2 (Pest)

- [ ] T044 [P] [US2] Write `tests/Feature/Modules/Communication/AdminChatModeration/FreezeChatTest.php` covering: happy path (status flip, frozen_at/by set, +1 audit row), idempotency (re-freeze no duplicate audit, no duplicate event), 422 when `reason_ar` missing, 422 on invalid category, 403 without `chat_moderation.freeze`, bilingual notification dispatches asserted via `Notification::fake()` and `Event::assertDispatched(ChatThreadFrozen::class)` exactly once.

### Implementation for User Story 2

- [ ] T045 [US2] Create Action `app/Modules/Communication/Application/Actions/FreezeChatAction.php` per contracts/freeze-thread.md (DB::transaction + lockForUpdate + idempotent short-circuit + `DB::afterCommit` event dispatch).
- [ ] T046 [US2] Create FormRequest `app/Modules/Communication/Http/Requests/Admin/FreezeChatRequest.php` with bilingual validation (`reason_en|min:5|max:500`, `reason_ar|min:5|max:500`, `category|in:off_platform_contact,policy_violation,harassment,other`). Add @bodyParam PHPDoc per contracts/freeze-thread.md.
- [ ] T047 [US2] Create controller `app/Modules/Communication/Http/Controllers/Admin/FreezeChatController.php` with 3-line action body that resolves DTO from request, calls `FreezeChatAction::execute`, and returns the resource.
- [ ] T048 [US2] Create API resource `app/Modules/Communication/Http/Resources/ChatThreadResource.php` for JSON response shape. Include @response PHPDoc per contracts/freeze-thread.md with realistic EN+AR example.
- [ ] T049 [US2] Register `POST /admin/api/chat-threads/{publicId}/freeze` in `app/Modules/Communication/Routes/admin.php`, gated by `auth:sanctum` + `can:chat_moderation.freeze`.
- [ ] T050 [US2] Create listener `app/Modules/Communication/Application/Listeners/DispatchChatThreadFrozenNotificationsListener.php` (implements `ShouldQueue`, queue `notifications`) that dispatches `chat.thread_frozen.customer` + `chat.thread_frozen.vendor` bilingual templates.
- [ ] T051 [US2] Add seeder rows for `notification_templates` covering `chat.thread_frozen` (push + in_app for customer + vendor) with bilingual JSON subject/body. Append to `app/Modules/Communication/Database/Seeders/NotificationTemplatesSeeder.php`.
- [ ] T052 [US2] Add a **Freeze** Filament action on the `ChatThreadResource::ViewRecord` page (modal with `reason_en`, `reason_ar`, `category` Select). Delegate to `FreezeChatAction::execute` in the closure; gate visibility on `chat_moderation.freeze`.
- [ ] T053 [US2] Run T044 — must pass.

**Checkpoint**: US2 fully functional. Admin can freeze a thread end-to-end via API and Filament UI.

---

## Phase 5: User Story 3 — Admin Unfreezes a Thread After Investigation (Priority: P1)

**Goal**: Bilingual-reason unfreeze that returns `status='open'`, clears `frozen_at`/`frozen_by`, refuses with 409 when `booking_vendor.sub_status NOT IN ('pending','modified')`, writes one audit row, dispatches bilingual notifications.

**Independent Test**: Freeze a thread (US2) → unfreeze → state reverts, audit and notifications fire; then put booking_vendor in `accepted` state → unfreeze returns 409 bilingual error.

### Tests for User Story 3 (Pest)

- [ ] T054 [P] [US3] Write `tests/Feature/Modules/Communication/AdminChatModeration/UnfreezeChatTest.php`: happy path within review window, 409 when `sub_status='accepted'`, 422 missing bilingual reason, 403 without permission, idempotency on already-open thread, +1 audit row per success.

### Implementation for User Story 3

- [ ] T055 [US3] Create Action `app/Modules/Communication/Application/Actions/UnfreezeChatAction.php` per contracts/unfreeze-thread.md (with `lockForUpdate` on booking_vendor and `ChatThreadUnfreezeForbidden` throw on bad sub_status).
- [ ] T056 [US3] Create FormRequest `app/Modules/Communication/Http/Requests/Admin/UnfreezeChatRequest.php` with @bodyParam docs.
- [ ] T057 [US3] Create controller `app/Modules/Communication/Http/Controllers/Admin/UnfreezeChatController.php` (3-line action body).
- [ ] T058 [US3] Register `POST /admin/api/chat-threads/{publicId}/unfreeze` in `app/Modules/Communication/Routes/admin.php`.
- [ ] T059 [US3] Create listener `app/Modules/Communication/Application/Listeners/DispatchChatThreadUnfrozenNotificationsListener.php` that fires `chat.thread_unfrozen.customer` + `chat.thread_unfrozen.vendor`.
- [ ] T060 [US3] Append `chat.thread_unfrozen` template seeds to `NotificationTemplatesSeeder` (push + in_app, customer + vendor, bilingual).
- [ ] T061 [US3] Add **Unfreeze** Filament action on `ChatThreadResource::ViewRecord` (visible only when `frozen_at IS NOT NULL`; gated on `chat_moderation.unfreeze`).
- [ ] T062 [US3] Run T054 — must pass.

**Checkpoint**: US3 fully functional. Admin can reverse a freeze when the review window is still open.

---

## Phase 6: User Story 4 — Admin Resolves an Auto-Flagged Suspicious Message (Priority: P1)

**Goal**: Admin closes an open `chat_moderation_flag` with a 4-decision enum and bilingual note. `upheld_redact` flips `chat_message_log.redacted=true` (the only allowed content-adjacent UPDATE). Re-resolve returns 409.

**Independent Test**: Seed an unreviewed flag → resolve with `upheld_redact` → assert flag.reviewed_at set, message_log.redacted=true, audit row written, all content-bearing columns on message_log unchanged.

### Tests for User Story 4 (Pest)

- [ ] T063 [P] [US4] Write `tests/Feature/Modules/Communication/AdminChatModeration/ResolveChatFlagTest.php` covering all four decisions, 409 on re-resolve, 422 on missing `note_ar`, 403 without permission, exactly +1 audit row per success.
- [ ] T064 [P] [US4] Write `tests/Feature/Modules/Communication/AdminChatModeration/NoChatContentMutationInvariantTest.php` — the invariant test required by SC-004 / FR-EXT-036-015. Static-style assertion: enumerate every Eloquent `update()` / `save()` call in the codebase against `ChatMessageLog` and assert only `flagged`/`flag_reason`/`redacted` are dirty. Additionally call the `updating` listener directly with a forbidden column to assert the exception fires.

### Implementation for User Story 4

- [ ] T065 [US4] Create Action `app/Modules/Communication/Application/Actions/ResolveChatFlagAction.php` per contracts/resolve-flag.md (lockForUpdate, throws `ChatFlagAlreadyResolved` on re-resolve, conditional `messageLog->update(['redacted' => true])` only on `UpheldRedact`).
- [ ] T066 [US4] Create FormRequest `app/Modules/Communication/Http/Requests/Admin/ResolveChatFlagRequest.php` (decision enum + bilingual note) with @bodyParam docs.
- [ ] T067 [US4] Create controller `app/Modules/Communication/Http/Controllers/Admin/ResolveChatFlagController.php`.
- [ ] T068 [US4] Register `POST /admin/api/chat-moderation-flags/{publicId}/resolve` in `app/Modules/Communication/Routes/admin.php`.
- [ ] T069 [US4] Create API resource `app/Modules/Communication/Http/Resources/ChatModerationFlagResource.php` with @response PHPDoc + bilingual example.
- [ ] T070 [US4] Create Filament resource `app/Modules/Communication/Filament/Resources/ChatModerationFlagResource.php` (list + view only). Navigation group "Communication / Moderation Flags", permission `chat_moderation.view` to view, `chat_moderation.resolve_flag` to resolve.
- [ ] T071 [US4] Create page `app/Modules/Communication/Filament/Resources/ChatModerationFlagResource/Pages/ListChatModerationFlags.php` with columns: thread link, flag_type badge, matched_pattern, action_taken, created_at, reviewed_at, reviewer. Filters: flag_type, reviewed (null/not-null), date range.
- [ ] T072 [US4] Create page `app/Modules/Communication/Filament/Resources/ChatModerationFlagResource/Pages/ViewChatModerationFlag.php` (Infolist). Add **Resolve Flag** row Action with bilingual note + decision Select; delegate to `ResolveChatFlagAction::execute`.
- [ ] T073 [US4] Run `shield:generate --all` to register `view_any_chat_moderation_flag` / `view_chat_moderation_flag` permissions; assign to `admin`.
- [ ] T074 [US4] Run T063 and T064 — must pass.

**Checkpoint**: US4 fully functional. Admin can resolve any flag with auditable bilingual notes and zero content mutation.

---

## Phase 7: User Story 5 — System Detects Phone / Email Patterns and Flags Messages (Priority: P1)

**Goal**: Queued `DetectSuspiciousMessageJob` runs on every new `chat_message_log` insert, applies the curated regex set (with Eastern-Arabic digit normalization), creates `chat_moderation_flags` rows idempotently per `(log, flag_type)`.

**Independent Test**: Insert 4 message rows (phone Latin, phone ٠١٠..., email, link, innocent) → dispatch job → assert 4 flag rows, fourth message unflagged, re-run produces no duplicates.

### Tests for User Story 5 (Pest)

- [ ] T075 [P] [US5] Write `tests/Unit/Modules/Communication/ChatModeration/MessagePatternDetectorTest.php` covering: Egyptian carriers 010/011/012/015 (Latin + Arabic digits), spaced/dashed/dotted variants, international E.164, email with + and dots, instaparty.eg link not flagged, external link flagged, innocent text returns empty array.
- [ ] T076 [P] [US5] Write `tests/Feature/Modules/Communication/AdminChatModeration/DetectSuspiciousMessageJobTest.php`: 4 insert + dispatch matrix from spec acceptance scenarios (phone Latin/Arabic, email, link, innocent), idempotency on retry — second dispatch creates 0 new flags.

### Implementation for User Story 5

- [ ] T077 [US5] Create job `app/Modules/Communication/Application/Jobs/DetectSuspiciousMessageJob.php` on the `chat-moderation` queue. Constructor takes `int $chatMessageLogId` + optional `?string $bodyPreview`. Inside: load the log row, call `MessagePatternDetector::detect`, for each match check `firstOrCreate` on `(chat_message_log_id, flag_type)`, update message log `flagged=true` + `flag_reason` (most-severe wins), fire `ChatMessageFlagged` for each new flag via `DB::afterCommit`.
- [ ] T078 [US5] Add a synthetic body-source for Phase 1: since Firestore is the source of truth, expose `DetectSuspiciousMessageJob::dispatchWithBody(int $logId, string $body)` as the public dispatch path the Firestore listener will use (the listener itself is upstream and out of scope; document the contract in research.md/R9 and ADR-0014).
- [ ] T079 [US5] Update `MessagePatternDetector` if needed to handle empty/whitespace bodies gracefully (return empty array, not throw).
- [ ] T080 [US5] Run T075 and T076 — must pass.

**Checkpoint**: US5 fully functional. Auto-detection annotates incoming messages without touching content.

---

## Phase 8: User Story 6 — Admin Marks a Confirmed Off-Platform Contact Attempt and Escalates to Inbox (Priority: P2)

**Goal**: Admin-driven manual flag path (`MarkOffPlatformContactAttemptAction`) plus escalation to Phase 6.1 inbox (`EscalateChatFlagToAdminInboxAction`). Both Actions idempotent.

**Independent Test**: Mark a non-regex message as off-platform → flag row + log annotation appear; click escalate → `admin_inbox_items` row created with correct source linkage; re-escalate reuses existing row.

### Tests for User Story 6 (Pest)

- [ ] T081 [P] [US6] Write `tests/Feature/Modules/Communication/AdminChatModeration/MarkOffPlatformContactTest.php`: happy path, idempotency on (log, flag_type) UNIQUE, 422 invalid flag_type / missing bilingual reason, 403 without permission, audit row delta of 1.
- [ ] T082 [P] [US6] Write `tests/Feature/Modules/Communication/AdminChatModeration/EscalateChatFlagTest.php`: creates an inbox item, routes to the assignee via `InboxRoutingRuleEngine`, idempotency reuses existing item, in-app notification dispatched, 422/403 negatives, audit delta of 1 on new escalate / 0 on reuse.

### Implementation for User Story 6

- [ ] T083 [US6] Create Action `app/Modules/Communication/Application/Actions/MarkOffPlatformContactAttemptAction.php` per contracts/mark-off-platform.md.
- [ ] T084 [US6] Create FormRequest `app/Modules/Communication/Http/Requests/Admin/MarkOffPlatformContactRequest.php` (flag_type whitelist excluding `profanity`, bilingual reason). Add @bodyParam docs.
- [ ] T085 [US6] Create controller `app/Modules/Communication/Http/Controllers/Admin/MarkOffPlatformContactController.php`.
- [ ] T086 [US6] Register `POST /admin/api/chat-message-logs/{publicId}/mark-off-platform` in `app/Modules/Communication/Routes/admin.php`.
- [ ] T087 [US6] Create Action `app/Modules/Communication/Application/Actions/EscalateChatFlagToAdminInboxAction.php` per contracts/escalate-to-inbox.md (delegates to `ChatModerationRoutingHelper` for assignee resolution).
- [ ] T088 [US6] Create FormRequest `app/Modules/Communication/Http/Requests/Admin/EscalateChatFlagRequest.php` (severity enum + bilingual summary). Add @bodyParam docs.
- [ ] T089 [US6] Create controller `app/Modules/Communication/Http/Controllers/Admin/EscalateChatFlagController.php`.
- [ ] T090 [US6] Register `POST /admin/api/chat-moderation-flags/{publicId}/escalate` in `app/Modules/Communication/Routes/admin.php`.
- [ ] T091 [US6] Create listener `app/Modules/Communication/Application/Listeners/DispatchChatFlagEscalatedNotificationsListener.php` that fires `chat.flag_escalated` in-app notification to the assignee admin.
- [ ] T092 [US6] Append `chat.flag_escalated` (in_app, admin audience, bilingual) row to `NotificationTemplatesSeeder`.
- [ ] T093 [US6] Create Filament resource `app/Modules/Communication/Filament/Resources/ChatMessageLogResource.php` (read-only — list + view only; assert no `CreateAction`, no `EditAction`, no `DeleteAction`, no `ReplicateAction`, no `ForceDeleteAction`, no inline editable columns). Navigation group "Communication / Chat Mirror".
- [ ] T094 [US6] Create pages `app/Modules/Communication/Filament/Resources/ChatMessageLogResource/Pages/ListChatMessageLogs.php` + `ViewChatMessageLog.php`. Add row Action **Mark as Off-Platform Attempt** delegating to `MarkOffPlatformContactAttemptAction`.
- [ ] T095 [US6] Add **Escalate to Admin Inbox** row Action on `ChatModerationFlagResource::ViewChatModerationFlag` modal delegating to `EscalateChatFlagToAdminInboxAction`.
- [ ] T096 [US6] Run `shield:generate --all` for `view_any_chat_message_log` / `view_chat_message_log`.
- [ ] T097 [US6] Run T081 and T082 — must pass.

**Checkpoint**: US6 fully functional. Manual flagging + escalation closes the loop with Phase 6.1 inbox.

---

## Phase 9: User Story 7 — Read-Only Audit Timeline on the Thread Detail Page (Priority: P2)

**Goal**: Thread detail page renders Booking Context + Read-Only Message View + Audit Timeline. Redacted messages display as `<message redacted by moderation>` bilingually. No edit/delete controls anywhere.

**Independent Test**: For a thread with freeze → resolve → unfreeze → escalate, open the detail view: 4 timeline entries appear sorted by `created_at ASC` with actor + bilingual reason; rendered HTML contains zero Edit/Delete buttons on messages or timeline.

### Tests for User Story 7 (Pest)

- [ ] T098 [P] [US7] Write `tests/Feature/Modules/Communication/AdminChatModeration/AuditTimelineTest.php` — seed a known 4-event sequence on a thread, assert all four entries render in order, asserts `assertSee` for actor + bilingual reason for each, and `assertDontSee('Edit')` / `assertDontSee('Delete')` on the rendered page HTML.
- [ ] T099 [P] [US7] Write `tests/Feature/Modules/Communication/AdminChatModeration/ChatMessageLogResourceReadOnlyTest.php` — GET to every possible Filament resource URL for `ChatMessageLogResource` (`/index`, `/create`, `/{id}/edit`) — assert `/index` and `/{id}` return 200, `/create` and `/{id}/edit` return 404. Same for `ChatThreadResource`.

### Implementation for User Story 7

- [ ] T100 [US7] Create page `app/Modules/Communication/Filament/Resources/ChatThreadResource/Pages/ViewChatThread.php` as a Filament Infolist with three sections:
  - **Booking Context**: booking ref, customer, vendor, product type of first booking item, current `chat_threads.status`, `frozen_at`, `frozen_by`.
  - **Read-Only Message View**: rendered from `$thread->messages` chronological. Each row shows sender name, timestamp (admin tz), message body. Redacted rows render as bilingual `<message redacted by moderation>` placeholder.
  - **Audit Timeline**: query `audit_logs` where `auditable_type` IN [`ChatThread`, `ChatMessageLog`, `ChatModerationFlag`] and the auditable id chain reaches this thread; render as `RepeatableEntry` sorted by `created_at ASC` with actor, action, bilingual reason, timestamp.
- [ ] T101 [US7] Ensure the view page has no Edit button in the page header; remove default Filament Edit/Delete header actions explicitly via `getHeaderActions(): array { return []; }`.
- [ ] T102 [US7] Run T098 and T099 — must pass.

**Checkpoint**: US7 fully functional. Admins have full read-only visibility with zero mutation paths through the UI.

---

## Phase 10: Polish & Cross-Cutting Concerns

**Purpose**: API docs, dev seeders, regenerate Shield once more for the complete resource set, ADR/CLAUDE.md links, quickstart validation.

- [ ] T103 [P] Create dev seeder `app/Modules/Communication/Database/Seeders/ChatModerationDevelopmentSeeder.php` that seeds 10 threads, 30 message logs (4 phone, 3 email, 2 link, 1 manual, 20 innocent), and dispatches `DetectSuspiciousMessageJob` synchronously in dev so flag rows materialize. Wire into `DatabaseSeeder` behind a `--class` invocation, not the default `db:seed`.
- [ ] T104 [P] Append to `.specify/memory/api-registry.md` entries for the five new endpoints with realistic EN+AR examples per quickstart.md §9.
- [ ] T105 [P] Create Bruno collection `docs/api/collections/admin-chat-moderation.bru` covering all five endpoints + 422 / 403 / 404 / 409 negative cases with bilingual payloads.
- [ ] T106 [P] Run `php artisan scribe:generate` and verify the generated docs include @bodyParam + @response for all five Form Requests + Resources.
- [ ] T107 Run `php artisan shield:generate --all` one final time to ensure all three new resources (`ChatThreadResource`, `ChatMessageLogResource`, `ChatModerationFlagResource`) are registered.
- [ ] T108 Run `./vendor/bin/pint` over `app/Modules/Communication/` and `tests/Feature/Modules/Communication/AdminChatModeration/` until clean.
- [ ] T109 Run `./vendor/bin/phpstan analyse app/Modules/Communication/` and resolve any new errors introduced by the feature.
- [ ] T110 Run the full feature test suite: `./vendor/bin/pest --filter="AdminChatModeration|MessagePatternDetector"` and confirm green.
- [ ] T111 Walk the quickstart.md §6 manual smoke test end-to-end against a fresh `php artisan migrate:fresh --seed` to confirm all five admin actions function in `/admin`.
- [ ] T112 Update `.specify/memory/project-index.md` to add spec `036-admin-chat-moderation` and ADR-0014 entries.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)** has no dependencies — T001–T014 can begin immediately.
- **Phase 2 (Foundational)** depends on Phase 1 completion — BLOCKS all user stories.
- **Phase 3 (US1)** depends on Phase 2; produces the index UI MVP.
- **Phase 4 (US2)** depends on Phase 2; can run in parallel with Phases 3, 5, 7 (different files).
- **Phase 5 (US3)** depends on Phase 2 + Phase 4 (Unfreeze is the reverse of Freeze; tests assert state after both run, but the Action files are independent).
- **Phase 6 (US4)** depends on Phase 2; can run in parallel with Phases 3, 4, 5, 7.
- **Phase 7 (US5)** depends on Phase 2; can run in parallel with all other user-story phases.
- **Phase 8 (US6)** depends on Phase 2 + Phase 6 (`EscalateChatFlagToAdminInboxAction` operates on a `ChatModerationFlag` that US6's `MarkOffPlatformContactAttemptAction` and US5's detection job produce; either upstream source is sufficient for the test seeds).
- **Phase 9 (US7)** depends on Phase 2 + Phase 3 (the detail page lives inside `ChatThreadResource`).
- **Phase 10 (Polish)** depends on all user-story phases being complete.

### User Story Dependencies

- US1 (P1): Foundational only.
- US2 (P1): Foundational only.
- US3 (P1): Foundational + US2 (uses its frozen-state setup).
- US4 (P1): Foundational only.
- US5 (P1): Foundational only.
- US6 (P2): Foundational + US4 (escalates a flag; flag creation belongs to US4 or US5).
- US7 (P2): Foundational + US1 (extends the same `ChatThreadResource`).

### Within Each User Story

- Pest test file written first (or alongside) and asserted FAILING before implementation lands — Constitution §VII discipline.
- Actions before controllers; controllers before route registration.
- FormRequests before controllers (controller depends on Form Request shape).
- Filament action wiring (UI) last in each phase.

### Parallel Opportunities

- **Phase 1**: T003–T013 are all `[P]` — different files, no cross dependencies.
- **Phase 2**: T015–T028, T031–T032, T035–T036 are all `[P]`.
- **Phase 3 ↔ 4 ↔ 5 ↔ 6 ↔ 7**: by different developers — they touch disjoint Action / Controller / Resource / Page files. Only the shared route file `Routes/admin.php` requires sequential edits (or merge resolution).
- **Phase 10**: T103–T106 are all `[P]`.

---

## Parallel Example: Phase 2 Foundational

```powershell
# Run after Phase 1 completes:
# All of these are independent files — launch in parallel:
Task: "Create ChatMessageLog model in app/Modules/Communication/Domain/Models/ChatMessageLog.php"
Task: "Create ChatModerationFlag model in app/Modules/Communication/Domain/Models/ChatModerationFlag.php"
Task: "Create FreezeChatDTO in app/Modules/Communication/Application/DTOs/FreezeChatDTO.php"
Task: "Create UnfreezeChatDTO in app/Modules/Communication/Application/DTOs/UnfreezeChatDTO.php"
Task: "Create ResolveChatFlagDTO in app/Modules/Communication/Application/DTOs/ResolveChatFlagDTO.php"
Task: "Create MarkOffPlatformContactDTO in app/Modules/Communication/Application/DTOs/MarkOffPlatformContactDTO.php"
Task: "Create EscalateChatFlagDTO in app/Modules/Communication/Application/DTOs/EscalateChatFlagDTO.php"
Task: "Create ChatThreadFrozen event in app/Modules/Communication/Domain/Events/ChatThreadFrozen.php"
Task: "Create ChatThreadUnfrozen event in app/Modules/Communication/Domain/Events/ChatThreadUnfrozen.php"
Task: "Create ChatModerationFlagResolved event in app/Modules/Communication/Domain/Events/ChatModerationFlagResolved.php"
Task: "Create MessagePatternDetector in app/Modules/Communication/Infrastructure/Services/MessagePatternDetector.php"
Task: "Create ChatModerationRoutingHelper in app/Modules/Communication/Infrastructure/Services/ChatModerationRoutingHelper.php"
```

---

## Implementation Strategy

### MVP (US1 only — read-only index)

1. Complete Phase 1 (Setup, T001–T014).
2. Complete Phase 2 (Foundational, T015–T036).
3. Complete Phase 3 (US1, T037–T043).
4. **STOP and VALIDATE**: admin sees the moderation index; permission gate works; sorting + filters work. Ship this as an internal preview if needed.

### Incremental Delivery — Recommended Order

1. Phase 1 + 2 → Foundation in place.
2. Phase 3 (US1) → Internal MVP — read-only index.
3. Phase 4 (US2) + Phase 5 (US3) → Freeze/Unfreeze live. Admin can act on a thread.
4. Phase 7 (US5) → Auto-detection live. Flags start appearing.
5. Phase 6 (US4) → Resolve flow closes flags. Now the queue stops growing.
6. Phase 8 (US6) → Manual mark + escalation. Edge-case coverage lands.
7. Phase 9 (US7) → Audit timeline + read-only message view. Full operator visibility.
8. Phase 10 → Polish + Scribe + ADR link + quickstart smoke test → ship.

### Parallel Team Strategy

After Phase 2 completes, four developers can run in parallel:

- Dev A: Phase 3 (US1) → Phase 9 (US7) — same `ChatThreadResource` lineage.
- Dev B: Phase 4 (US2) → Phase 5 (US3) — Freeze/Unfreeze pair.
- Dev C: Phase 6 (US4) → Phase 8 (US6) — Flag lifecycle + escalation.
- Dev D: Phase 7 (US5) — Detection job + regex.

Only `Routes/admin.php` and the `NotificationTemplatesSeeder` require coordinated edits (or sequential PR merges).

---

## Validation Checklist (run before marking the feature done)

- [ ] All 112 tasks above are checked off.
- [ ] `php artisan migrate:fresh --seed` succeeds on a clean DB and the 10-thread dev seeder lands without error.
- [ ] `./vendor/bin/pest --filter="AdminChatModeration|MessagePatternDetector"` is fully green.
- [ ] `./vendor/bin/pint` and `./vendor/bin/phpstan analyse app/Modules/Communication/` are clean.
- [ ] `php artisan shield:generate --all` runs without changes (idempotent).
- [ ] ADR-0014 committed and linked from `CLAUDE.md`.
- [ ] `.specify/memory/api-registry.md` lists the five new endpoints.
- [ ] Bruno collection `docs/api/collections/admin-chat-moderation.bru` covers happy + negative cases.
- [ ] Manual smoke test per `quickstart.md §6` passes for all five admin actions.
- [ ] Inviolable: searching the codebase for `chat_message_log` UPDATEs reveals only `flagged`/`flag_reason`/`redacted` are written post-insert.

---

## Notes

- Every Pest test file lives under `tests/Feature/Modules/Communication/AdminChatModeration/` or `tests/Unit/Modules/Communication/ChatModeration/` per the per-module test convention in `02_Tech_Decisions.md`.
- All FormRequests carry @bodyParam PHPDoc for Scribe; all API Resources carry @response PHPDoc with realistic EN+AR data.
- All bilingual reason fields are required by FormRequest validation — Constitution §IV.
- No new packages; no new ENUM values on `chat_threads.status`; no Phase 2 features.
- Inviolable admin boundary: no Action / Controller / Filament page / Console command writes content fields of `chat_message_log` after insert. Verified by T064.
