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

# Feature Specification: Vendor Booking Chat Panel (RestrictedChatPanel)

**Feature Branch**: `036-admin-chat-moderation`
**Created**: 2026-05-16
**Status**: Draft
**Phase**: Phase 8.2 — Restricted Chat, Compliance & Off-Platform Prevention

---

## Traceability & Scope

- **PRD coverage**: No specific FR in `docs/specs/01_PRD.md` covers the vendor-facing chat UI directly; the closest is the booking communication policy and the Admin Journey anti-pattern rules. This spec defines local `FR-EXT-037-NNN` requirements. ⚠️ BACKFILL NEEDED: add vendor restricted-chat FRs to `01_PRD.md`.
- **Vendor Journey coverage**: `docs/specs/07_Vendor_Journey.md` step 11 — *"Restricted chat with customer (only during review window)"* (line 140) and *"chat only open while `booking_vendors.sub_status IN ('pending', 'modified')`"* (line 198).
- **Admin Journey coverage**: `docs/specs/08_Admin_Journey.md` line 257 — *"Prevent off-platform communication: phone & email regex filter on chat; flag suspicious messages for review."*
- **Phase alignment**: **Phase 8.2 — Restricted Chat, Compliance & Off-Platform Prevention** (`docs/specs/09_Phasing_Plan.md`). Vendor panel is the parallel deliverable to spec 036 (admin moderation UI) in the same phase.
- **Depends on**:
  - `specs/036-admin-chat-moderation/` — provides `chat_threads`, `chat_message_log`, `chat_moderation_flags` migrations, `FreezeChatAction`, `ResolveChatFlagAction`, and the off-platform regex set.
  - `specs/030-vendor-booking-decision-page/` — `VendorBookingDecisionPage` and `VendorBookingDetailPage` are the host pages.
  - Phase 5.0 — Notifications infrastructure (bilingual freeze/block notifications).
  - Firestore chat gateway stub — `FirestoreChatGatewayStub` already exists at `app/Modules/Communication/Infrastructure/Gateways/FirestoreChatGatewayStub.php`.
- **Schema traceability**:
  - `chat_threads` — `status` (`open`/`locked`/`closed`), `frozen_at`, `frozen_by`, `booking_id`, `vendor_profile_id` (migrations `2026_05_16_100003` and `2026_05_16_100004`).
  - `chat_message_log` — append-only message mirror (migration written by spec 036; this feature consumes it read-only and as insert target via the gateway).
  - `chat_moderation_flags` — one row per blocked or suspicious message (migration written by spec 036; this feature inserts rows on vendor-side blocks).
  - `booking_vendors` — `sub_status` drives the review-window guard.
  - `audit_logs` — append-only; a row is written on every blocked-message attempt and on every successful message send.
- **Out of scope**:
  - The Firestore real-time listener (out-of-scope per spec 036 assumption).
  - Admin-side freeze/unfreeze UI (spec 036).
  - Customer-facing chat panel (Phase 2 mobile/web app surface).
  - Chat media (images, voice) — Firebase Storage is Phase 2.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Vendor Sends a Clean Message During the Review Window (Priority: P1)

A vendor opens their booking detail or decision page while a pending booking is awaiting their response. A **Restricted Chat** section is visible below the booking info, showing the message history (mirrored from Firestore via `chat_message_log`) and a compose form. The vendor types a question for the customer ("Can you confirm the full delivery address?"), clicks **Send**, and the message is delivered. The compose form clears and the new message appears in the message list.

**Why this priority**: This is the core happy path of the restricted-chat feature. Without it, the entire compliance infrastructure (blocking, freezing, flagging) has no context to operate in — vendors can't communicate and the value proposition of "restricted but functional chat during review" collapses.

**Independent Test**: Seed one `chat_thread` with `status='open'` and no `frozen_at` linked to a pending `booking_vendor`. Log in as the owning vendor. Open the booking detail page. Type "Can you confirm the address?" in the compose form. Click Send. Assert: a new `chat_message_log` row is written with the correct `chat_thread_id`, `sender_id`, and the clean body; no `chat_moderation_flags` row is created; the Firestore gateway stub's `sendMessage()` is called once; an `audit_logs` row records `chat.message_sent`.

**Acceptance Scenarios**:

1. **Given** an `open` thread linked to a pending booking_vendor, **When** the vendor types a clean message and submits, **Then** `SendVendorChatMessageAction` is called, the gateway stub writes to Firestore, a `chat_message_log` mirror row is inserted, an `audit_logs` entry is written, and the compose form clears.
2. **Given** the vendor submits a blank message, **When** the form validates, **Then** a validation error ("Message cannot be empty" / "لا يمكن أن تكون الرسالة فارغة") is shown client-side and no action is executed.
3. **Given** the vendor submits a message longer than 1,000 characters, **When** the form validates, **Then** a "Message too long" error is shown and no action is executed.

---

### User Story 2 — Vendor Message Is Blocked for Off-Platform Contact Pattern (Priority: P1)

A vendor types a message containing their phone number: "اتصل بي على 0101-234-5678" (trying to bypass the platform). The compose form performs a client-side hint but the **server-side** `SendVendorChatMessageAction` runs the same curated regex set used by the admin moderation job (Egyptian mobile pattern, email, external link). The message is blocked: no Firestore write occurs, a `chat_moderation_flags` row is created with `flag_type='phone'`, `action_taken='block'`, the message log row is written with `flagged=true`, and the vendor sees a bilingual error toast: **"Your message was blocked: external contact details are not permitted" / "تم حظر رسالتك: لا يُسمح بمعلومات الاتصال الخارجية"**.

**Why this priority**: Off-platform contact prevention is the compliance purpose of the entire chat feature. Blocking at send time (P1) is more effective than flagging after delivery. Without this, the restricted chat is no restriction at all.

**Independent Test**: Seed an `open` thread. Submit a message containing "01012345678". Assert: no Firestore gateway call for message delivery; `chat_message_log` row inserted with `flagged=true`, `flag_reason='phone_pattern'`; a `chat_moderation_flags` row created with `flag_type='phone'`, `action_taken='block'`; an `audit_logs` row records `chat.message_blocked`; the vendor receives a 422 or in-Filament error notification.

**Acceptance Scenarios**:

1. **Given** a message containing an Egyptian mobile number (Latin or Eastern Arabic digits, with or without dashes/spaces), **When** the vendor submits it, **Then** the message is blocked, `chat_moderation_flags` has a new `phone` row with `action_taken='block'`, and the vendor sees the bilingual block toast.
2. **Given** a message containing an email address, **When** the vendor submits, **Then** the message is blocked with `flag_type='email'`.
3. **Given** a message containing an external link (non-`instaparty.eg`), **When** the vendor submits, **Then** the message is blocked with `flag_type='external_link'`.
4. **Given** the same message pattern submitted twice (retry), **When** `SendVendorChatMessageAction` runs, **Then** each attempt creates a new `chat_moderation_flags` row (each attempt is a distinct event), and neither reaches Firestore.
5. **Given** a message with Eastern Arabic digits (٠١٠١٢٣٤٥٦٧٨), **When** submitted, **Then** it is blocked with the same result as the Latin-digit equivalent — normalization runs before the regex.

---

### User Story 3 — Chat Panel Shows Freeze Banner When Admin Has Frozen the Thread (Priority: P1)

Admin froze a thread (spec 036's `FreezeChatAction`). The vendor now opens the booking detail page. The chat panel renders in a **frozen state**: the message history is visible (read-only), the compose form is hidden, and a **bilingual admin-freeze banner** is shown at the top of the panel. The banner text reads: **"This chat has been frozen by the platform administrator. You may not send new messages until the freeze is lifted." / "تم تجميد هذه المحادثة من قِبَل مسؤول المنصة. لا يمكنك إرسال رسائل جديدة حتى يتم رفع التجميد."** The banner also shows the freeze timestamp. If the freeze has a public-facing reason (admin opted in — flagged per spec 036 on future enhancement, defaulting to generic message in Phase 1), it is shown below the banner body.

**Why this priority**: The freeze banner is the vendor's only signal that admin has intervened. Without it, a vendor will keep trying to send messages and receive confusing errors with no explanation of what happened.

**Independent Test**: Seed a thread with `status='locked'` and `frozen_at` set. Open the booking detail page as the owning vendor. Assert: compose form is absent from the DOM; freeze banner is rendered with both EN and AR text; the frozen-at timestamp is visible; no JS or HTTP attempt to send a message succeeds (the action guard rejects any attempt to `SendVendorChatMessageAction` on a locked thread).

**Acceptance Scenarios**:

1. **Given** a thread with `frozen_at IS NOT NULL`, **When** the vendor opens the chat panel, **Then** the compose form is not rendered, the bilingual freeze banner is shown with the freeze timestamp, and any direct attempt to invoke `SendVendorChatMessageAction` returns a 403 with bilingual reason.
2. **Given** the thread is later unfrozen by admin (spec 036's `UnfreezeChatAction`), **When** the vendor reloads the panel, **Then** the freeze banner disappears and the compose form is restored — provided the booking is still within the review window.
3. **Given** a thread with `status='locked'` but `frozen_at IS NULL` (system lock, not admin freeze), **When** the vendor opens the panel, **Then** a generic "Chat temporarily unavailable" indicator is shown (not the admin-freeze banner), and the compose form is hidden.

---

### User Story 4 — Chat Panel Is Closed After the Review Window Ends (Priority: P1)

The booking vendor moves past the review window — `booking_vendors.sub_status` transitions to `accepted`, `rejected`, or any non-review value. The vendor opens the booking detail page. The chat panel shows the full message history in read-only mode. The compose form is absent. A status label reads: **"Chat closed — booking no longer in review" / "المحادثة مغلقة — الحجز لم يعد قيد المراجعة"**.

**Why this priority**: The chat must be strictly bounded to the review window. Allowing messages post-decision breaks the booking lifecycle and undermines off-platform-contact prevention.

**Independent Test**: Seed a thread with `status='open'` and `booking_vendor.sub_status='accepted'`. Open the chat panel. Assert: compose form is absent; status label shows "closed" message in both locales; any direct server-side call to `SendVendorChatMessageAction` returns a guard error.

**Acceptance Scenarios**:

1. **Given** `booking_vendor.sub_status = 'accepted'`, **When** the chat panel loads, **Then** the compose form is absent and the "chat closed" label is rendered.
2. **Given** `booking_vendor.sub_status = 'rejected'`, **When** the chat panel loads, **Then** the same read-only state is applied.
3. **Given** `bookings.lifecycle_status = 'cancelled'`, **When** the chat panel loads, **Then** the panel shows "Chat closed — booking cancelled" / "المحادثة مغلقة — الحجز ملغى" and the compose form is absent.
4. **Given** the thread `status='closed'` (system-set at booking completion), **When** the chat panel loads, **Then** the read-only state applies regardless of `sub_status`.

---

### User Story 5 — Wrong Vendor Cannot Access Another Vendor's Chat (Priority: P1)

A vendor pastes a booking detail URL belonging to a booking assigned to a different vendor. They expect to see (and interact with) that other vendor's restricted chat. Instead, a 403 is returned before any data renders. The chat thread data, message history, and customer details of the other vendor's booking are never exposed.

**Why this priority**: Cross-vendor chat exposure is a critical data-privacy and compliance risk. This boundary must be enforced server-side at every data-loading call, not just at page-mount time.

**Independent Test**: Seed booking_vendor A (vendor 1) with a chat thread. Log in as vendor 2. Navigate to vendor 1's booking detail URL. Assert HTTP 403. Also attempt a direct Livewire/form submission to `SendVendorChatMessageAction` with vendor 1's `chat_thread_id`. Assert 403 with no state mutation.

**Acceptance Scenarios**:

1. **Given** vendor 2 authenticated, **When** they request the booking detail page for a booking_vendor belonging to vendor 1, **Then** the response is HTTP 403 and no booking or chat data is rendered.
2. **Given** vendor 2 authenticated, **When** they directly call `SendVendorChatMessageAction` with a `chat_thread_id` belonging to vendor 1's thread, **Then** the action aborts with 403 and no `chat_message_log` row is written.
3. **Given** an unauthenticated request, **When** the booking detail URL is accessed, **Then** the request is redirected to the vendor login page (no 403, no data leakage).

---

### Edge Cases

- **Thread not yet created for this booking**: A new booking_vendor may exist before a `chat_thread` row is created (e.g., if the Firestore listener hasn't fired yet). The chat panel renders a "Chat not yet available — please refresh shortly" placeholder with no error.
- **Multiple booking_vendors on the same booking (multi-vendor event)**: Each vendor sees only the thread scoped to their `vendor_profile_id`. Different vendors' threads are completely separate and never cross-displayed.
- **Vendor sends a message at the exact moment admin freezes the thread**: The freeze uses a `SELECT ... FOR UPDATE` lock (per spec 036 FreezeChatAction). The vendor's `SendVendorChatMessageAction` checks thread status inside the same transaction; whichever starts first wins. If the send wins and the freeze wins after, the message is in the log and the thread becomes frozen — no race corruption.
- **Message preview truncation in the message list**: If `chat_message_log.message_kind='text'` but the body is very long (>500 chars), the panel truncates to 200 chars with a "show more" toggle. Blocked messages are displayed as `<message blocked by compliance>` instead of the original body.
- **Firestore gateway unavailable**: If `FirestoreChatGateway::sendMessage()` throws, `SendVendorChatMessageAction` must NOT write the `chat_message_log` mirror row for the failed message. The transaction rolls back. The vendor sees a "Could not send — please try again" error. No half-written state.
- **Vendor account suspended mid-chat**: If `vendor_profiles.approval_status` is set to `suspended`, the page mount aborts with 403 before any chat data renders.
- **Arabic and mixed-locale messages**: The `detected_locale` field in `chat_message_log` is set by the gateway based on content. The UI displays messages as-is; no locale processing of message body content is done server-side.
- **Simultaneous sends**: Livewire wire:loading prevents double-submit on the client side; the server guard in `SendVendorChatMessageAction` is the authoritative check.

---

## Requirements *(mandatory)*

### Functional Requirements

**Panel surface**

- **FR-EXT-037-001**: System MUST embed a `RestrictedChatPanel` section on both `VendorBookingDetailPage` and `VendorBookingDecisionPage`. The panel MUST be the last section on both pages. ⚠️ BACKFILL NEEDED.
- **FR-EXT-037-002**: The panel MUST be implemented as a Livewire component (or Filament `Section` with custom Blade view) inside `app/Modules/Communication/` — NOT duplicated into `app/Modules/Booking/`. The Booking pages include it via component reference.
- **FR-EXT-037-003**: The panel MUST load message history from `chat_message_log` ordered by `created_at ASC`, limited to the most recent 50 messages on initial load. Older messages are accessed via a "Load earlier messages" control (non-paginated, single additional batch of 50).
- **FR-EXT-037-004**: Each message in the list MUST display: sender label ("You" for the vendor, customer display name for the customer), message body (or the compliance-block placeholder), and a timestamp formatted as "d M Y, H:i" in the panel's current locale. Blocked messages MUST display `[Message blocked — compliance]` / `[رسالة محجوبة — امتثال]` in place of body text.
- **FR-EXT-037-005**: The panel MUST display a clearly labeled **chat status indicator** in its header — one of: Open (success color), Frozen by Admin (danger color), Closed (gray color), or System-Locked (warning color).

**State machine**

- **FR-EXT-037-010**: System MUST determine the panel state from `chat_threads.status`, `chat_threads.frozen_at`, and `booking_vendors.sub_status` using this precedence (top wins):
  1. Thread missing (not yet created) → `placeholder` state.
  2. `frozen_at IS NOT NULL` → `frozen` state (regardless of `status`).
  3. `status = 'closed'` OR `booking_vendor.sub_status NOT IN ('pending', 'modified')` OR `bookings.lifecycle_status IN ('cancelled', 'completed')` → `closed` state.
  4. `status = 'locked'` AND `frozen_at IS NULL` → `system_locked` state.
  5. `status = 'open'` AND `booking_vendor.sub_status IN ('pending', 'modified')` → `open` state.
- **FR-EXT-037-011**: The compose form (Textarea + Send button) MUST only render when `panelState = 'open'`.
- **FR-EXT-037-012**: The admin-freeze banner MUST only render when `panelState = 'frozen'`. The banner MUST include: the bilingual freeze reason (if one was recorded in the `audit_logs` metadata for `action='chat.frozen'` linked to this thread) and the `frozen_at` timestamp. If no public-facing reason is available (spec 036 Phase 1 default), the banner shows the generic bilingual fallback text defined in FR-EXT-037-012-fallback.

**Sending messages**

- **FR-EXT-037-020**: System MUST implement `SendVendorChatMessageAction` (one `execute(ChatThread $thread, VendorProfile $vendor, string $body): ChatMessageLog`). The action MUST:
  1. Verify `$thread->vendor_profile_id === $vendor->id` — abort 403 if mismatch.
  2. Verify `panelState = 'open'` by running the state check described in FR-EXT-037-010 — abort 422 with bilingual guard message if not open.
  3. Run the off-platform regex set (same curated set used in spec 036's moderation job, extracted to a shared `OffPlatformPatternDetector` service) with Eastern Arabic digit normalization.
  4. If a pattern matches: (a) insert `chat_message_log` with `flagged=true`, `flag_reason=<type>`; (b) insert `chat_moderation_flags` with `flag_type=<type>`, `action_taken='block'`; (c) write `audit_logs` row `action='chat.message_blocked'`; (d) do NOT call the Firestore gateway; (e) return with a 422 + bilingual error.
  5. If no pattern matches: (a) call `FirestoreChatGateway::sendMessage()`; (b) insert `chat_message_log` with `flagged=false`; (c) write `audit_logs` row `action='chat.message_sent'`; (d) return the new `ChatMessageLog` model.
  6. Wrap steps 3–5 in `DB::transaction`; do not call the gateway inside the transaction — call it in step 5a after the DB rows are committed (or use `DB::afterCommit` for the gateway call to preserve rollback safety on DB failure).
- **FR-EXT-037-021**: `SendVendorChatMessageAction` MUST be idempotent on network retries: if the Livewire component sends a duplicate request within 10 seconds with the same body, return the already-written `ChatMessageLog` row without re-inserting (detected via a short-lived Redis key keyed on `chat_thread_id + vendor_id + body_hash`). ⚠️ PHASE BACKFILL NEEDED — short-lived Redis dedup is a simplification because the full `idempotency_keys` table pattern requires a 24h TTL and is reserved for payment-path endpoints per Constitution §VIII. Document this in the ADR.
- **FR-EXT-037-022**: The compose form MUST enforce client-side length validation: body required, max 1,000 characters. The server-side `SendVendorChatMessageAction` MUST enforce the same constraints independently (defense in depth).
- **FR-EXT-037-023**: System MUST extract `OffPlatformPatternDetector` as a shared service class (not a private method inside the Action) so spec 036's moderation job and this action both use the identical regex set with no duplication. `OffPlatformPatternDetector` lives in `app/Modules/Communication/Application/Services/`.

**Authorization**

- **FR-EXT-037-030**: Panel mount MUST verify: (a) authenticated user owns a `VendorProfile`; (b) `booking_vendor.vendor_profile_id === auth()->user()->vendorProfile->id`; (c) `chat_thread.vendor_profile_id === auth()->user()->vendorProfile->id`. Abort 403 on any mismatch.
- **FR-EXT-037-031**: `SendVendorChatMessageAction` MUST re-verify ownership on every call — bypassing the mount-time check via a direct Livewire round-trip MUST NOT allow cross-vendor send.
- **FR-EXT-037-032**: Vendors with a suspended `VendorProfile` (`approval_status = 'suspended'`) MUST receive a 403 at mount time, before any chat data is loaded.

**Audit**

- **FR-EXT-037-040**: Every successful message send MUST produce an `audit_logs` row: `action='chat.message_sent'`, `subject_type=ChatMessageLog`, `subject_id=<id>`, `created_by=<vendor_user_id>`.
- **FR-EXT-037-041**: Every blocked message MUST produce an `audit_logs` row: `action='chat.message_blocked'`, `subject_type=ChatModerationFlag`, `subject_id=<flag_id>`, `created_by=<vendor_user_id>`, `metadata` includes `flag_type` and `matched_pattern`.
- **FR-EXT-037-042**: Page renders (panel loads) MUST NOT write to `audit_logs`. Only sends and blocks are audited.

**Bilingual & localisation**

- **FR-EXT-037-050**: All panel labels, banners, status indicators, error toasts, and validation messages MUST have both EN and AR translations under `app/Modules/Communication/Resources/lang/{en,ar}/chat.php`.
- **FR-EXT-037-051**: The freeze banner MUST render in the vendor portal's current locale (EN or AR). The Filament vendor panel already uses the locale switcher from spec 036.
- **FR-EXT-037-052**: Message timestamps MUST be displayed in the admin's current locale format (`d M Y, H:i` for EN, and the Arabic equivalent formatted string for AR). Raw UTC is never shown.

**Tests**

- **FR-EXT-037-060**: Pest test file at `tests/Feature/Modules/Communication/VendorRestrictedChat/` MUST cover:
  - Send clean message happy path (open thread, pending booking_vendor).
  - Send blocked on phone pattern (Latin digits, Eastern Arabic digits separately).
  - Send blocked on email pattern.
  - Send blocked on external link pattern.
  - Panel state = `frozen` when `frozen_at IS NOT NULL` → compose form absent.
  - Panel state = `closed` when `sub_status = 'accepted'` → compose form absent.
  - Panel state = `closed` when `lifecycle_status = 'cancelled'` → compose form absent.
  - Wrong vendor: direct call to `SendVendorChatMessageAction` with mismatched `vendor_profile_id` → 403.
  - Unauthenticated request → redirect to login.
  - Blocked message creates exactly one `chat_moderation_flags` row and one `audit_logs` row.
  - Successful send creates zero `chat_moderation_flags` rows and one `audit_logs` row.
  - Gateway failure rolls back DB state (no orphan `chat_message_log` row).

### Key Entities *(include if feature involves data)*

- **ChatThread** (existing, migrations from spec 036): `status`, `frozen_at`, `frozen_by`, `vendor_profile_id`, `customer_id`, `booking_id`. This feature does NOT add columns to `chat_threads`.
- **ChatMessageLog** (existing in schema doc, migrations from spec 036): Append-only. Vendor sends insert rows here. After insert, only `flagged`, `flag_reason`, `redacted` are mutable (per Constitution §V and spec 036 FR-EXT-036-015). `sender_id`, `firestore_message_id`, `message_kind`, `detected_locale` are insert-only.
- **ChatModerationFlag** (existing in schema doc, migrations from spec 036): One row per blocked/suspicious message. This feature inserts rows at send time for blocked messages; spec 036 resolves them from the admin side.
- **BookingVendor** (existing): `sub_status` drives the review-window guard. Read-only from this feature's perspective.
- **AuditLog** (existing): Every send and block writes a row. Append-only.
- **OffPlatformPatternDetector** (new service class): Shared regex engine used by both this Action and spec 036's moderation job. Lives in `app/Modules/Communication/Application/Services/`. ⚠️ NEW CLASS — extract from spec 036's job if that job is written first, or author it here first and have spec 036 depend on it.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A vendor with an open booking can compose and send a clean message from the booking detail or decision page in **under 5 seconds** end-to-end (panel render + send round-trip) on a standard broadband connection.
- **SC-002**: **100% of off-platform-contact messages** (phone, email, external link — Latin and Eastern Arabic digits) submitted by vendors are blocked **before** reaching the Firestore gateway, verified by integration tests asserting zero gateway calls on blocked payloads.
- **SC-003**: Every blocked message produces exactly one `chat_moderation_flags` row and one `audit_logs` row with `action='chat.message_blocked'` — zero double-inserts under any retry scenario.
- **SC-004**: Panel renders in `frozen` state for **100% of threads** where `frozen_at IS NOT NULL`, with no compose form in the DOM — verified by Pest visiting the page as vendor and asserting form absence.
- **SC-005**: Cross-vendor access attempts produce **zero successful sends and zero data exposures** — verified by the wrong-vendor tests asserting 403 on every data-access path.
- **SC-006**: Both EN and AR renderings of the panel — including the freeze banner, blocked-message placeholder, and status indicator — pass a manual locale-switch QA without missing keys or layout regressions.
- **SC-007**: `OffPlatformPatternDetector` is implemented as a single shared class with a dedicated unit-test file covering all regex patterns, confirmed by grep showing zero duplicate regex definitions across spec 036 and this spec.

---

## Assumptions

- The migrations for `chat_message_log` and `chat_moderation_flags` are authored in spec 036 and available before this spec's implementation begins. If spec 036 migrations are absent, stub factories are used in tests.
- The `FirestoreChatGatewayStub` already implements a `sendMessage(string $threadId, string $senderId, string $body): string` method returning a `firestore_message_id`. If the method signature differs, this spec adapts to the existing stub's contract.
- A `chat_thread` row is pre-created (by a listener or by spec 036's migration seeder) when a booking is submitted. If the thread row does not exist for a given booking, the panel renders the "Chat not yet available" placeholder rather than erroring.
- The vendor portal Filament locale switcher (spec 036's Language Switch plugin setup) is already configured so `app()->getLocale()` returns the correct locale inside vendor panel pages.
- Redis is available for the short-lived dedup key (FR-EXT-037-021). The key TTL is 10 seconds, far below the 24h `idempotency_keys` TTL, so it does not use that table.
- ADR-0014 (authored in spec 036) covers the overall chat compliance architecture. This spec adds an "Internal Decision" entry to ADR-0014 covering the vendor-side send path and the shared `OffPlatformPatternDetector` extraction.
- Phase ID is **8.2**. No phasing backfill required.
- This is a **Filament vendor portal page section**, not a public REST API endpoint. No new REST routes, OpenAPI entries, or `api-registry.md` entries are introduced.
