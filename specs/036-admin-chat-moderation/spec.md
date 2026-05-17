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
- FR traceability: cite specific FR numbers from 01_PRD.md, or define local FR-EXT-NNN with a "⚠️ BACKFILL NEEDED: add to 01_PRD.md" note. Never leave requirements untraced.
- Schema traceability: cite existing tables from 11_DB_Schema.md. New tables flagged "⚠️ NEW TABLE — not yet in 11_DB_Schema.md".
- Phase alignment: cite Phase ID from 09_Phasing_Plan.md, or propose extension with "⚠️ PHASE BACKFILL NEEDED" note.
- Never suggest a package not in 10_Package_List.md.
- Never suggest a Phase 2 feature.
- Never contradict docs/specs/02_Tech_Decisions.md locked stack.

API DOCUMENTATION CONSTRAINT:
- Every API endpoint generated must include:
  - @bodyParam PHPDoc on every Form Request field (Scribe-compatible)
  - @response PHPDoc with realistic EN+AR example data on every Resource
  - Entry added to .specify/memory/api-registry.md
  - Bruno/Postman collection entry in docs/api/collections/
---

# Feature Specification: Admin Restricted Chat Moderation UI

**Feature Branch**: `036-admin-chat-moderation`
**Created**: 2026-05-16
**Status**: Draft
**Input**: User description: "Build Admin Restricted Chat Moderation UI. Admin can monitor restricted booking chat, detect off-platform contact, freeze chat, resolve flags, and keep audit logs. Admin must never edit chat message content."

## Traceability & Scope

- **PRD coverage**: No numbered FR in `docs/specs/01_PRD.md` covers admin chat oversight directly; the closest is the NFR for auditability and the Admin Journey rule line 257: *"Admin **never** edits customer-vendor chat content (only freezes/audits) — Trust + audit integrity"* (`docs/specs/08_Admin_Journey.md`). This spec defines local `FR-EXT-036-NNN` requirements. ⚠️ BACKFILL NEEDED: add admin chat moderation FRs to `01_PRD.md`.
- **Admin Journey coverage**: `docs/specs/08_Admin_Journey.md` steps 10, 14, 15 — *"Monitor restricted chat as needed; keep audit logs"*, *"Monitor compliance"*, *"Prevent off-platform communication: phone & email regex filter on chat; flag suspicious messages for review"* (lines 140–176, 244, 257).
- **Vendor Journey coverage**: `docs/specs/07_Vendor_Journey.md` step 11 — *"Restricted chat with customer (only during review window)"* (line 140) and the rule that chat is only open while `booking_vendors.sub_status IN ('pending','modified')` (line 198).
- **Phase alignment**: **Phase 8.2 — Restricted Chat, Compliance & Off-Platform Prevention** (`docs/specs/09_Phasing_Plan.md` line 1681). This spec is the admin-UI deliverable for that phase. ADR required per phase plan: `ADR-0014-chat-compliance-admin-oversight.md`.
- **Schema traceability**:
  - Reuses existing `chat_threads` (already created in `2026_05_16_100003_create_chat_threads_table.php` with `status ENUM('open','locked','closed')`, `frozen_at`, `frozen_by` columns from the follow-up migration).
  - Reuses `audit_logs` (append-only, polymorphic).
  - Reuses `admin_inbox_items` (Phase 6.1) for escalation routing.
  - Introduces (migration not yet written, present in `11_DB_Schema.md` §Communication lines 1131–1159 but not yet a Laravel migration): `chat_message_log`, `chat_moderation_flags`. Schema columns are locked per `11_DB_Schema.md`; this spec only adds the migration files and code that consume them. ⚠️ SCHEMA-DOC OK / ⚠️ MIGRATIONS MISSING (will be authored under this feature).
- **Locked Tech Decision (`docs/specs/02_Tech_Decisions.md` line 290)**: *"Chat moderation: Phone/email regex blocker, runs as queue job before delivery."* Real chat is in Firestore; MySQL stores only the audit mirror. This spec MUST NOT introduce a `chat_messages` table or admin write-paths into Firestore.
- **Inviolable Admin Boundary (`docs/specs/08_Admin_Journey.md` line 257)**: Admin can freeze, unfreeze, redact, flag, resolve, and escalate, but **MUST NEVER edit or delete original message content**. There is no admin edit endpoint, no admin edit Filament action, and no admin-facing Firestore write tool.
- **Distinct from spec 019 (Admin Inbox Routing)**: Spec 019 = inbox queue that aggregates intervention items. This spec = moderation surface specifically for chat. The escalation Action (`EscalateChatFlagToAdminInboxAction`) feeds spec 019's inbox; it does not duplicate it.
- **Depends on**:
  - Phase 5.0 — Notifications infrastructure (for vendor/customer "thread frozen" alerts)
  - Phase 6.1 — Admin Inbox Routing (spec 019) for escalation target
  - Firestore listener writing to `chat_message_log` (out-of-scope here; assumed available)

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Admin Monitors Restricted Chat Threads From a Single Filament Page (Priority: P1)

A platform admin opens the **Restricted Chat** Filament page under the *Communication* navigation group. They see a paginated table of `chat_threads` showing one row per booking-scoped thread, with columns: booking reference (clickable to `BookingResource`), customer name, vendor display name, **chat status pill** (`open` / `locked` / `closed` / `frozen`), **open-flag count** (badge — number of `chat_moderation_flags` rows where `reviewed_at IS NULL`), **last message at** (relative time, e.g., "2h ago"), **frozen by / frozen at** (only when `frozen_at IS NOT NULL`). They can filter by chat status, by "has open flags", by booking lifecycle status, by product type (joined through `bookings → booking_items.product_type`), and by date range on `last_message_at`. The default sort is `frozen_at DESC NULLS LAST, last_message_at DESC` so frozen and recently active threads bubble to the top.

**Why this priority**: This is the entry point for every admin action in this feature. Without the index page, freeze/flag/escalate have no surface and chat compliance is invisible. It is also the smallest independently shippable slice — a read-only moderation index has value on day one even before any action wiring lands.

**Independent Test**: Seed 10 chat threads across all three product types — 2 frozen, 3 with open flags, 5 healthy. Log in as an admin with `chat_moderation.view` permission. Open the Restricted Chat page. Verify all 10 rows render, frozen threads sort first, flag-count badges show correct integers, the product-type filter narrows the list correctly, and the "has open flags" toggle hides healthy threads.

**Acceptance Scenarios**:

1. **Given** 10 chat threads with mixed statuses, **When** the admin loads the Restricted Chat page, **Then** the table renders all rows, sorts frozen-then-recent-first, and each cell — booking ref, customer, vendor, status pill, flag count, last message time, frozen-by/at — populates from the documented relations without N+1 (eager-load `customer`, `vendorProfile.user`, `booking`, `frozenByUser`, and a count of unresolved `chat_moderation_flags`).
2. **Given** the admin filters by product type = `rental`, **When** the table reloads, **Then** only threads whose `bookings.id` has at least one `booking_items.product_type='rental'` are shown.
3. **Given** the admin clicks a row, **When** the detail page opens, **Then** it routes to `ChatThreadResource::ViewRecord` (Filament Infolist) and does NOT route to an edit form — there is no edit form for `chat_threads`.
4. **Given** an admin with only the `vendor_management.view` permission and no chat permission, **When** they navigate to the Restricted Chat page, **Then** they receive a 403 and the page does not appear in the navigation group.

---

### User Story 2 — Admin Freezes a Thread Suspected of Off-Platform Coordination (Priority: P1)

While reviewing a thread on the detail page, the admin sees a flagged message that contains what looks like a phone number formatted with spaces to evade the regex ("0 1 0 - 1 2 3 ..."). They click **Freeze Chat**. A confirmation modal asks for a **bilingual freeze reason** (`reason_en` and `reason_ar`, both required) and a **freeze category** (`off_platform_contact` / `policy_violation` / `harassment` / `other`). On submit, `FreezeChatAction` executes inside a `DB::transaction`: it sets `chat_threads.status='locked'`, `frozen_at=now()`, `frozen_by=auth()->id()`, writes an `audit_logs` row (`subject_type=ChatThread`, `action=chat.frozen`, `metadata` carries reason and category), and (via `DB::afterCommit`) fires `ChatThreadFrozen` which triggers bilingual `chat.thread_frozen.customer` and `chat.thread_frozen.vendor` notification templates. The Firestore client SDKs see the new `locked` status on their next listen and refuse further sends. The admin returns to the index page and the thread now shows the frozen pill and the frozen-by/at columns.

**Why this priority**: Freeze is the irreversible-feeling lever that lets admin actually stop a violation in progress. Without it the moderation surface is purely observational.

**Independent Test**: Seed an `open` thread with no frozen state. As an admin with `chat_moderation.freeze` permission, call `FreezeChatAction` (or click Freeze in the UI). Verify (a) `chat_threads.status='locked'`, `frozen_at` and `frozen_by` are populated, (b) an `audit_logs` row exists with `action='chat.frozen'` and the bilingual reason in `metadata`, (c) `ChatThreadFrozen` event fires exactly once after commit, (d) two notification dispatches are queued (one customer, one vendor), and (e) re-running `FreezeChatAction` on the same already-frozen thread returns the existing state without writing a duplicate audit row.

**Acceptance Scenarios**:

1. **Given** an `open` thread, **When** the admin submits the Freeze modal with both EN and AR reason filled, **Then** the action succeeds, status flips to `locked`, `frozen_at` and `frozen_by` are set, and exactly one new audit row is written.
2. **Given** an `open` thread, **When** the admin submits the Freeze modal with only EN text filled, **Then** the FormRequest rejects with a 422 demanding both locales (bilingual-first rule from Constitution §IV) — no DB write occurs.
3. **Given** a `locked` thread, **When** the admin clicks Freeze again, **Then** the action returns idempotently (existing `frozen_at` preserved) and no duplicate audit row is written.
4. **Given** an admin without `chat_moderation.freeze` permission, **When** they POST to the freeze action, **Then** they receive a 403 and no state change occurs.

---

### User Story 3 — Admin Unfreezes a Thread After Investigation Clears It (Priority: P1)

After investigation, an admin determines the flagged content was a false positive (a vendor sharing their own venue address, which is allowed). They open the thread, click **Unfreeze**, fill a bilingual `unfreeze_reason_en` and `unfreeze_reason_ar`, and submit. `UnfreezeChatAction` sets `status='open'` (only if `booking_vendors.sub_status IN ('pending','modified')` — otherwise the thread cannot re-open per Vendor Journey rule line 198), clears `frozen_at` and `frozen_by`, writes an `audit_logs` row (`action='chat.unfrozen'`), and fires `ChatThreadUnfrozen` to notify both parties bilingually.

**Why this priority**: Freeze without unfreeze becomes a permanent ban-by-mistake. The reversibility is part of the admin trust contract.

**Independent Test**: Freeze a thread (Story 2), then call `UnfreezeChatAction`. Verify status flips to `open`, `frozen_at` clears, both parties get bilingual notifications, and an audit row records the unfreeze with the bilingual reason.

**Acceptance Scenarios**:

1. **Given** a `locked` thread whose booking_vendor is still in review (`sub_status='pending'`), **When** the admin unfreezes with bilingual reason, **Then** status returns to `open`, `frozen_at`/`frozen_by` are nulled, and an audit row records the unfreeze.
2. **Given** a `locked` thread whose booking has already moved past the review window (`sub_status='accepted'`), **When** the admin unfreezes, **Then** the action returns a 409 with bilingual message *"Cannot reopen chat after review window closed"* / *"لا يمكن إعادة فتح المحادثة بعد انتهاء فترة المراجعة"* and the thread stays `locked` (Vendor Journey rule line 198).
3. **Given** an admin without `chat_moderation.unfreeze` permission, **When** they POST to the unfreeze action, **Then** they receive a 403.

---

### User Story 4 — Admin Resolves an Auto-Flagged Suspicious Message (Priority: P1)

A customer sent a message reading "call me on 0100-123-4567" and the Firestore listener's regex blocker auto-created a `chat_moderation_flag` (`flag_type='phone'`, `matched_pattern='0100-123-4567'`, `action_taken='warn'`). The admin opens the thread, sees the flag in the inline audit timeline, and clicks **Resolve Flag**. A modal asks for a **resolution decision** (`upheld_redact` / `upheld_warn` / `upheld_block` / `dismissed_false_positive`) and a bilingual **resolution note**. `ResolveChatFlagAction` sets `chat_moderation_flags.reviewed_by`, `reviewed_at`, and (if the decision flips the action) updates `action_taken`. It also sets `chat_message_log.redacted=true` when the decision is `upheld_redact` — redact means the audit mirror's message becomes hidden in admin and party UIs, but the original row is **never UPDATED to change the text and never DELETED** (append-only contract from `CLAUDE.md §15`). An audit log row is always written.

**Why this priority**: Open flags pile up if there is no resolve path. Closing them is what turns the queue from anxiety-inducing into a working tool.

**Independent Test**: Seed a `chat_moderation_flag` with `reviewed_at=NULL`. As admin with `chat_moderation.resolve_flag` permission, call `ResolveChatFlagAction` with each of the four decisions on separate flags. Verify the flag row's `reviewed_by` and `reviewed_at` are populated, the original `chat_message_log` row is **not** mutated in its content fields (only `redacted` may flip), and an audit row is written per resolution.

**Acceptance Scenarios**:

1. **Given** an unreviewed `chat_moderation_flag`, **When** the admin resolves it with decision `upheld_redact`, **Then** `flag.reviewed_by`, `flag.reviewed_at`, `flag.action_taken='redact'` are set; the linked `chat_message_log.redacted=true`; an audit row records the resolution with the bilingual note.
2. **Given** an unreviewed flag, **When** the admin resolves it with `dismissed_false_positive`, **Then** the flag is marked reviewed but `chat_message_log.redacted` stays `false` and no message text is touched.
3. **Given** an already-resolved flag (`reviewed_at IS NOT NULL`), **When** the admin tries to resolve it again, **Then** the action returns a 409 with bilingual *"Flag already resolved"* and writes no new audit row.
4. **Given** any resolution path, **When** the action runs, **Then** no SQL statement in the transaction reads as an UPDATE on `chat_message_log.firestore_message_id`, `chat_message_log.sender_id`, or any content-bearing field — only `redacted` and `flagged` may be touched.

---

### User Story 5 — System Detects Phone / Email Patterns and Flags Messages (Priority: P1)

Whenever the Firestore listener writes a new row into `chat_message_log`, a queued moderation job runs against the message preview (if available). The job applies a curated regex set: Egyptian mobile patterns including spaced/dotted obfuscation (`/(?:\+?20|0)?\s*1\s*[0-2,5]\s*\d(?:[\s.\-]*\d){7}/`), international E.164 (`/\+\d{10,15}/`), email (`/[\w.+-]+@[\w-]+\.[\w.-]+/i`), and external link (`/https?:\/\/(?!instaparty\.eg)/i`). On match, the job sets `chat_message_log.flagged=true` and `flag_reason` (`phone_pattern` / `email_pattern` / `manual` / `external_link`), then inserts a `chat_moderation_flags` row with `flag_type`, `matched_pattern`, `action_taken='warn'` by default, and `reviewed_at=NULL`. The thread's open-flag count badge increments in the next admin index render. The original message body in Firestore is **not modified**; the platform only annotates its MySQL mirror.

**Why this priority**: Detection is what makes the moderation queue useful. Without it admin would have to read every conversation by hand. This is also the only piece that needs to be in place before Stories 2–4 are demonstrable on real traffic.

**Independent Test**: Insert one `chat_message_log` row containing "call me 01012345678", another containing "email me at ali@example.com", a third with "join https://wa.me/1234", and a fourth innocent message. Dispatch the moderation job. Verify the first three are flagged with the correct `flag_reason` and produce `chat_moderation_flags` rows; the fourth is untouched.

**Acceptance Scenarios**:

1. **Given** a new `chat_message_log` row with body "call me on 0 1 0 1 2 3 4 5 6 7 8", **When** the moderation job runs, **Then** `flagged=true`, `flag_reason='phone_pattern'`, and a `chat_moderation_flags` row with `flag_type='phone'` and `matched_pattern` containing the captured digits is inserted.
2. **Given** an email pattern, **When** the job runs, **Then** `flag_type='email'`.
3. **Given** a link to a non-instaparty.eg domain, **When** the job runs, **Then** `flag_type='external_link'`.
4. **Given** an innocent message ("happy birthday!"), **When** the job runs, **Then** no flag row is created and `flagged` stays `false`.
5. **Given** the same `chat_message_log` row is re-evaluated by a retry, **When** the job runs twice, **Then** at most one `chat_moderation_flags` row exists for that `chat_message_log_id` per `flag_type` (idempotency).

---

### User Story 6 — Admin Marks a Confirmed Off-Platform Contact Attempt and Escalates to Inbox (Priority: P2)

The admin opens a thread, identifies a confirmed off-platform contact attempt (e.g., a vendor explicitly inviting a customer to "DM me on Instagram @vendorhandle"), and clicks **Mark as Off-Platform Contact Attempt**. This runs `MarkOffPlatformContactAttemptAction` which: creates a `chat_moderation_flags` row with `flag_type='external_link'` (or `'other'` when no pattern matched but admin confirms intent) and `action_taken='block'`, sets `chat_message_log.flagged=true` and `flag_reason='manual'`, and writes an `audit_logs` row tagging the offender (`subject_type=User`, `subject_id=offender_user_id`). The admin then optionally clicks **Escalate to Admin Inbox**, which calls `EscalateChatFlagToAdminInboxAction` to create an `admin_inbox_items` row (spec 019) with `item_type='chat_violation'`, severity (`high`/`medium`/`low`), a bilingual summary, and a deep link back to the thread.

**Why this priority**: This is the human-override path for cases the regex cannot catch. P2 because the regex auto-flag (Story 5) covers the high-volume case; this is the long-tail.

**Independent Test**: Open a thread message that does NOT match any regex (e.g., "follow me on the gram"). As admin, mark it as an off-platform attempt and then escalate. Verify (a) a `chat_moderation_flags` row exists with `flag_reason='manual'` (on the log) and `flag_type` resolved correctly, (b) an `admin_inbox_items` row is created and routed by Phase 6.1 rules, and (c) audit log entries record both actions.

**Acceptance Scenarios**:

1. **Given** a `chat_message_log` not previously flagged, **When** admin marks it as off-platform attempt with bilingual reason, **Then** a flag row is inserted with `flag_type` matching the admin's selection, the message log row's `flagged=true` and `flag_reason='manual'`, and an audit row is written.
2. **Given** a flag created above, **When** admin clicks Escalate, **Then** an `admin_inbox_items` row is created with `item_type='chat_violation'` and `payload_json` containing thread/booking/offender identifiers, and an audit row links the flag to the inbox item.
3. **Given** an already-escalated flag, **When** admin clicks Escalate again, **Then** the existing inbox item is reused (idempotent by `flag_id`) and no duplicate is created.

---

### User Story 7 — Read-Only Audit Timeline on the Thread Detail Page (Priority: P2)

The admin opens any thread and sees, alongside the messages, an **Audit Timeline** panel showing every moderation event for this thread in chronological order: thread created, message flagged (with `flag_reason`), thread frozen by `<admin name>` at `<timestamp>` with reason, flag resolved by `<admin name>` with decision, thread unfrozen, escalated to inbox, etc. Each entry shows a timestamp in the admin's timezone, the actor, the action, and the bilingual reason when present. The timeline is built from `audit_logs` filtered by `subject_type IN ('ChatThread','ChatMessageLog','ChatModerationFlag')` joined to the thread's IDs, plus the relevant `chat_moderation_flags` rows. The panel is strictly read-only.

**Why this priority**: Audit visibility is required for the "keep audit logs" leg of Admin Journey step 10, and for vendor disputes that come back weeks later. P2 because Stories 1–5 already write the audit rows; this story is the consumption surface.

**Independent Test**: For a thread with a known sequence (freeze → resolve flag → unfreeze → escalate), open the detail page. Verify the timeline shows exactly those four events in order with the right actors, timestamps, and bilingual reasons. Verify no edit/delete controls render anywhere on the timeline.

**Acceptance Scenarios**:

1. **Given** a thread with 4 known audit events, **When** the admin opens the detail page, **Then** the timeline renders all 4 events sorted by `created_at ASC`, each showing actor, action, timestamp, and bilingual reason where applicable.
2. **Given** the same page, **When** the admin inspects the rendered HTML, **Then** no `<button>` / `<a>` exists with text "Edit" or "Delete" on any timeline entry or message in the read-only view.

---

### Edge Cases

- **Firestore listener lag**: A message may exist in Firestore for several seconds before its `chat_message_log` mirror lands. The admin index polls `chat_threads.updated_at`/`last_message_at`; no admin action can target a message that hasn't yet been mirrored to MySQL.
- **Soft-deleted booking or vendor**: When the underlying `bookings` or `vendor_profiles` row is soft-deleted, the thread row still exists. The admin index shows it with a "Deleted booking" badge but blocks Unfreeze (no booking_vendor to validate `sub_status`).
- **Customer or vendor user soft-deleted (GDPR-style request)**: The thread row remains, but customer/vendor name columns show `<deleted user>`. Audit rows still resolve via `created_by` FK with nullable display.
- **Two admins try to freeze the same thread concurrently**: First write wins via SELECT ... FOR UPDATE inside `FreezeChatAction`; the second admin gets the idempotent return path (acceptance scenario 3 of Story 2).
- **Flag attached to a `chat_message_log` whose `chat_thread_id` no longer exists**: Should not happen (FK restrictOnDelete), but if a backfill bug produces an orphan, the index hides it and a daily sweeper job logs the orphan to `audit_logs` for investigation.
- **A locked thread receives a message via Firestore due to client-side race**: The listener still mirrors it into `chat_message_log` but the moderation job sets `flagged=true` and `flag_reason='post_lock'` so admin sees the leak.
- **Regex false positives on Arabic numerals**: The phone regex accepts Eastern Arabic digits (٠–٩) via a normalization step before pattern match, so Arabic users typing "اتصل بي على ٠١٠١٢٣٤٥٦٧٨" are flagged the same as Latin.
- **Bulk freeze across many threads**: Out of scope; the admin must freeze one thread at a time so each carries an explicit reason. A future bulk path would require a separate ADR.
- **Admin tries to access a thread for a booking they have no permission scope for**: Filament policy denies the row and 403s the detail page.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-EXT-036-001**: System MUST provide a Filament page `ChatThreadResource` under `app/Modules/Communication/Filament/Resources/` listing all `chat_threads` rows with columns: booking reference, customer, vendor, status pill, open-flag count, last-message-at, frozen-by, frozen-at. The resource MUST be view-only (no `EditAction`, no `CreateAction`, no `DeleteAction`). ⚠️ BACKFILL NEEDED.
- **FR-EXT-036-002**: System MUST eager-load `customer`, `vendorProfile.user`, `booking`, `frozenByUser`, and a counted relation `unresolvedFlagsCount` on the index page to keep query count constant regardless of row count.
- **FR-EXT-036-003**: System MUST provide filters for chat status (`open`/`locked`/`closed`/`frozen`-as-derived), open-flag presence, product type (joined via booking_items), and a date range on `last_message_at`.
- **FR-EXT-036-004**: System MUST provide a `ChatThreadResource::ViewRecord` Infolist page with a **Booking Context** section, a **Read-Only Message View** section (rendered from `chat_message_log` in chronological order, showing sender, timestamp, body — with redacted messages displayed as `<message redacted by moderation>` in both locales), and an **Audit Timeline** section.
- **FR-EXT-036-005**: System MUST provide a `ChatMessageLogResource` Filament resource that is read-only across list and view; create/edit/delete pages MUST be absent. The resource MUST register navigation under "Communication / Chat Mirror" and MUST NOT expose Create, Edit, Replicate, ForceDelete, or any inline editable column.
- **FR-EXT-036-006**: System MUST provide a `ChatModerationFlagResource` Filament resource with list and view pages, scoped row actions (`resolve`, `escalateToInbox`), and bulk actions (`bulkResolveAsFalsePositive`, `bulkEscalate`). No edit form for flag content — only the resolution decision and bilingual note may be captured via the Resolve modal.
- **FR-EXT-036-007**: System MUST implement `FreezeChatAction` (per `.claude/rules/actions.md`) — one `execute(ChatThread $thread, FreezeChatDTO $dto): ChatThread`, wrapped in `DB::transaction`, taking `(reason_en, reason_ar, category)`. The Action MUST be idempotent on already-frozen threads. The Action MUST fire `ChatThreadFrozen` via `DB::afterCommit`.
- **FR-EXT-036-008**: System MUST implement `UnfreezeChatAction` with the same shape. The Action MUST block unfreeze when `booking_vendors.sub_status NOT IN ('pending','modified')` and MUST return a 409 with bilingual message in that case.
- **FR-EXT-036-009**: System MUST implement `ResolveChatFlagAction` taking `(ChatModerationFlag $flag, ResolveChatFlagDTO $dto)`. The Action MUST never UPDATE content-bearing fields of `chat_message_log` (`sender_id`, `firestore_message_id`, `message_kind`, `detected_locale`). It MAY toggle `redacted` and `flagged` on `chat_message_log`, and MUST set `reviewed_by`, `reviewed_at`, `action_taken` on the flag.
- **FR-EXT-036-010**: System MUST implement `MarkOffPlatformContactAttemptAction` for the admin-driven manual flag path. The Action MUST create a new `chat_moderation_flags` row with `flag_type` chosen by admin and `action_taken='block'`.
- **FR-EXT-036-011**: System MUST implement `EscalateChatFlagToAdminInboxAction` that creates an `admin_inbox_items` row of `item_type='chat_violation'` linked by `chat_moderation_flag_id` (idempotent — re-running on the same flag reuses the existing inbox item). The Action MUST integrate with the Phase 6.1 inbox routing rule engine.
- **FR-EXT-036-012**: System MUST run a queued moderation job (queue name `chat-moderation`) on every new `chat_message_log` row that applies the curated regex set (phone E.164 + Egyptian variants, email, external link, social handle hints), sets `flagged` / `flag_reason`, and inserts `chat_moderation_flags` rows. Job MUST be idempotent per `(chat_message_log_id, flag_type)`.
- **FR-EXT-036-013**: System MUST normalize Eastern Arabic digits (٠–٩) to Latin digits before applying the phone regex so Arabic-typed numbers are detected.
- **FR-EXT-036-014**: System MUST write an `audit_logs` row for every state transition: thread frozen, thread unfrozen, flag resolved, manual flag inserted, flag escalated to inbox. Each audit row MUST include the actor `user_id`, the polymorphic subject (`ChatThread` / `ChatModerationFlag`), and the bilingual reason in `metadata`.
- **FR-EXT-036-015**: System MUST enforce that no admin endpoint, Filament action, Console command, or queue job mutates the content fields of `chat_message_log` after insert. Only `redacted` (bool) and `flagged` (bool) + `flag_reason` (string) may be UPDATEd. This MUST be enforced at the Action layer (no such code path written) and documented as an invariant in the ADR.
- **FR-EXT-036-016**: System MUST require both `*_en` and `*_ar` fields on every reason input across Freeze, Unfreeze, Resolve, Mark, Escalate Form Requests. Missing either locale MUST return 422 (bilingual-first rule, Constitution §IV).
- **FR-EXT-036-017**: System MUST guard each Action behind a Spatie permission: `chat_moderation.view`, `chat_moderation.freeze`, `chat_moderation.unfreeze`, `chat_moderation.resolve_flag`, `chat_moderation.mark_off_platform`, `chat_moderation.escalate`. Unauthorized access MUST return 403 and the corresponding Filament action button MUST be `->visible(fn () => auth()->user()->can('...'))`.
- **FR-EXT-036-018**: System MUST send bilingual notifications via existing `NotificationDispatch` infrastructure on freeze, unfreeze, and escalate events. Templates referenced: `chat.thread_frozen.customer`, `chat.thread_frozen.vendor`, `chat.thread_unfrozen.customer`, `chat.thread_unfrozen.vendor`, `chat.flag_escalated.admin_inbox`.
- **FR-EXT-036-019**: System MUST surface a "frozen" status pill on the index page when `frozen_at IS NOT NULL`, distinct from the raw `status='locked'` pill, so admins can tell admin-initiated freezes apart from system locks at the end of the review window.
- **FR-EXT-036-020**: System MUST publish Pest test coverage in `tests/Feature/Modules/Communication/AdminChatModeration/` covering freeze happy path, freeze idempotency, freeze 422 missing AR reason, freeze 403 unauthorized; unfreeze happy path and 409 outside review window; flag pattern detection (phone, email, external link, innocent control); resolve action's no-content-mutation invariant; escalation idempotency; admin cannot edit message content (assertion that no edit route exists on `ChatMessageLogResource` and `ChatThreadResource`).

### Key Entities *(include if feature involves data)*

- **ChatThread (existing)**: Per-booking restricted chat session. Owns `status` (`open` / `locked` / `closed`), `frozen_at`, `frozen_by`, `customer_id`, `vendor_profile_id`, `booking_id`. This feature does NOT add columns.
- **ChatMessageLog (existing in schema doc, migration pending)**: Append-only mirror of each Firestore message. Owns `chat_thread_id`, `firestore_message_id`, `sender_id`, `message_kind`, `detected_locale`, `flagged`, `flag_reason`, `redacted`, `created_at`. INSERT-only beyond `flagged`, `flag_reason`, `redacted`.
- **ChatModerationFlag (existing in schema doc, migration pending)**: One per detected or admin-flagged issue. Owns `chat_message_log_id`, `flag_type` (`phone` / `email` / `profanity` / `external_link` / `other`), `matched_pattern`, `action_taken` (`redact` / `warn` / `block` / `none`), `reviewed_by`, `reviewed_at`, `created_at`.
- **AuditLog (existing)**: Polymorphic, append-only. Captures every freeze, unfreeze, resolve, mark, escalate action. Subject types relevant here: `ChatThread`, `ChatModerationFlag`, `ChatMessageLog` (read-only annotation events).
- **AdminInboxItem (existing — Phase 6.1)**: Escalation target. Receives `item_type='chat_violation'` rows from `EscalateChatFlagToAdminInboxAction`.
- **NotificationDispatch (existing)**: Used for bilingual freeze/unfreeze/escalation notifications.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Admin can locate a frozen or flagged thread from the moderation index in **under 10 seconds** for a database of 50,000 chat threads, with no manual page navigation beyond the index filters.
- **SC-002**: 100% of phone-number messages in the test corpus (Latin and Eastern Arabic digits, with and without spaces/dashes) are auto-flagged within **5 seconds** of `chat_message_log` insert. False positive rate on a curated 200-message innocent corpus is **≤ 2%**.
- **SC-003**: Every freeze, unfreeze, flag resolution, manual flag, and escalation produces exactly one corresponding `audit_logs` row — measured by integration test asserting `audit_logs` count delta of 1 per action and zero content-field UPDATEs on `chat_message_log`.
- **SC-004**: 0 code paths in the admin codebase mutate `chat_message_log.sender_id`, `firestore_message_id`, `message_kind`, or `detected_locale` after insert — verified by a static Pest assertion enumerating allowed UPDATE columns for the model.
- **SC-005**: Notifications fire bilingually for 100% of freeze/unfreeze/escalate events — both `en` and `ar` notification dispatch rows exist for each event, verified per acceptance test.
- **SC-006**: All Filament action buttons (Freeze, Unfreeze, Resolve, Mark, Escalate) hide automatically when the user lacks the corresponding `chat_moderation.*` permission — verified by Pest visiting the page as users with and without each permission.
- **SC-007**: Admin can never reach an Edit form for a chat message — verified by Pest sending GET to every Filament resource URL pattern under `ChatMessageLogResource` and `ChatThreadResource` and asserting only `index` and `view` return 200 (and `create`, `edit` return 404 or are absent).

## Assumptions

- The Firestore client/listener that mirrors messages into `chat_message_log` exists or will exist before this admin UI ships. This spec covers admin behavior only; if the listener is absent, Story 5 cannot run end-to-end and Stories 1–4 / 6–7 still work against seeded data.
- The migrations for `chat_message_log` and `chat_moderation_flags` follow the column shapes locked in `docs/specs/11_DB_Schema.md` lines 1131–1159. This feature creates those migrations under `app/Modules/Communication/Database/Migrations/` if they do not already exist.
- The existing `chat_threads.status='locked'` value is the "frozen" state. The UI surfaces a distinct "frozen" pill based on `frozen_at IS NOT NULL` rather than introducing a new status value (avoids ENUM migration). This honors Tech Decisions §1 and Schema Cheat Sheet's locked status enum.
- Phase 6.1 Admin Inbox Routing (spec 019) provides the `admin_inbox_items` table, routing rules, and assignment logic; this feature only inserts into it.
- The `chat_moderation` permission group will be added to the seeders under `app/Modules/Communication/Database/Seeders/`.
- ADR-0014 will be authored in the same feature branch before merge.
- Egyptian mobile carrier patterns (010/011/012/015 prefixes) are the primary detection target; the regex set is curated in code and reviewed in the ADR, not configurable from admin UI in Phase 1 (Phase 2 work).
- No real Firestore writes from admin: redaction is a MySQL-mirror annotation only; if the customer/vendor mobile clients still cache the unredacted body, that's an accepted limitation documented in the ADR.
