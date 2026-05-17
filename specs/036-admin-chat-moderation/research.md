# Phase 0 Research: Admin Restricted Chat Moderation UI

**Feature**: `036-admin-chat-moderation`
**Date**: 2026-05-16

---

## R1 — Where the moderation surface lives (Filament vs custom page)

**Decision**: Use **three first-class Filament Resources** — `ChatThreadResource`, `ChatMessageLogResource`, `ChatModerationFlagResource` — over the established `app/Modules/Communication/Filament/Resources/` discovery path. Resolve/Escalate are exposed as Filament **row Actions** (per `.claude/rules/filament-components.md` §2) bound to dedicated `App\\Modules\\Communication\\Application\\Actions\\*` classes. The Restricted Chat detail view is a Filament Infolist, not a custom Blade page.

**Rationale**:
- The Communication module already has resources for `AdminInboxResource`, `NotificationDispatchResource`, `CampaignResource` — keeping the moderation surface in the same shape preserves consistency, lets Shield handle permissions uniformly, and makes navigation grouping under "Communication" trivial.
- Filament Infolist is the canonical way to render read-only detail pages (see `filament-components.md` §3) — it gives us the audit timeline + booking context + message view layout for free without a custom Blade view.
- Row Actions delegate to the Application Action layer per `filament-components.md` §7 rule 8, so business logic stays out of the closure.

**Alternatives considered**:
- *Single custom Filament Page (`ChatModerationQueuePage.php`)* — rejected. Loses the resource-level Shield permission generation and forces us to hand-roll list filters, sorting, and pagination that the Resource gives us free.
- *Three Filament Pages with a shared layout* — rejected. Same reason.

---

## R2 — Distinguishing admin "frozen" from system "locked" without adding a chat status value

**Decision**: Keep the existing `chat_threads.status ENUM('open','locked','closed')` unchanged. Treat `frozen_at IS NOT NULL` (already shipped in migration `2026_05_16_100004_add_frozen_to_chat_threads.php`) as the canonical signal that the lock was admin-initiated. The admin index renders a distinct "Frozen" pill when `frozen_at IS NOT NULL`, falling back to "Locked" when `status='locked' AND frozen_at IS NULL` (i.e., the review window closed by lifecycle).

**Rationale**:
- The `status` enum is locked by `11_DB_Schema.md`; adding a `frozen` value would require an enum migration that drifts from the schema doc.
- `frozen_at` + `frozen_by` is already the columns the schema doc anticipated for admin freezes.
- UI semantics: "Frozen by admin" tells operators someone deliberately stopped the conversation; "Locked" alone signals the natural end of the review window. Conflating them loses signal.

**Alternatives considered**:
- *Add `frozen` value to the ENUM* — rejected (schema-drift, requires `11_DB_Schema.md` revision).
- *Add a separate `freeze_status` ENUM column* — rejected as overkill; the two timestamp columns already tell us.

---

## R3 — Append-only enforcement on `chat_message_log`

**Decision**: Enforce the append-only contract at **three independent layers**:

1. **Application layer** — no Action writes a content field; Pest invariant test enumerates all UPDATE statements that touch `chat_message_log` and asserts the changed-column set is a subset of `{flagged, flag_reason, redacted}`.
2. **Model layer** — `ChatMessageLog::$fillable` whitelists only `flagged`, `flag_reason`, `redacted` for mass assignment after initial insert. Content fields (`sender_id`, `firestore_message_id`, `message_kind`, `detected_locale`, `chat_thread_id`, `created_at`) are guarded via the model's `$guarded` declaration and a `creating`/`updating` Eloquent event listener that throws on attempted writes to forbidden columns.
3. **ADR-0014** — documents the invariant and the test that proves it. (No DB trigger in Phase 1; deferred to a Phase 2 hardening pass if the invariant test ever flakes.)

**Rationale**:
- The schema doc lists `chat_message_log` as append-only (`schema-cheatsheet.md` §"Append-only tables").
- A DB trigger is the strongest enforcement, but `02_Tech_Decisions.md §4` already lists `wallet_ledger` as the only DB-trigger-protected table; adding another trigger now would burn capacity better spent on the Phase 8.2 cut-list. The three-layer guard is "trust but verify" — verified by a unit test that lists every `UPDATE chat_message_log` query in the codebase.

**Alternatives considered**:
- *DB trigger as the only enforcement* — rejected. Hides the invariant from PHP developers reading the model; the model whitelist is the canonical readable contract.
- *Static analysis (PHPStan) only* — rejected. Doesn't catch raw query builder UPDATEs.

---

## R4 — Regex set + Eastern Arabic digit normalization

**Decision**: Detection lives in `App\\Modules\\Communication\\Infrastructure\\Services\\MessagePatternDetector`. The service exposes one public method `detect(string $body): array` that returns a list of `MatchedPattern` value objects (`flag_type`, `matched_pattern`, `flag_reason`). Before matching, the body is normalized: Eastern Arabic digits (٠–٩) → Latin digits, ZWNJ/ZWJ/NBSP stripped, sequences of whitespace collapsed to single spaces.

**Regex set (curated, reviewed in ADR-0014)**:

| flag_type | flag_reason | Pattern |
|---|---|---|
| `phone` | `phone_pattern` | `/(?:\+?20|0)?\s*1\s*[0125]\s*\d(?:[\s.\-]*\d){7}/u` (Egyptian mobile) |
| `phone` | `phone_pattern` | `/\+\d{10,15}/u` (international E.164 fallback) |
| `email` | `email_pattern` | `/[\w.+-]+@[\w-]+\.[\w.-]+/iu` |
| `external_link` | `external_link` | `/https?:\/\/(?!(?:www\.)?instaparty\.eg)[^\s]+/iu` |
| `other` | `manual` | not auto-matched — admin-driven via `MarkOffPlatformContactAttemptAction` |

**Rationale**:
- Egyptian carrier prefixes 010/011/012/015 are the high-volume case; the regex tolerates spaces, dots, and dashes between digits to defeat the most common obfuscation.
- Eastern Arabic digit normalization is required because Egyptian users frequently type numbers as ٠١٠١٢٣٤٥٦٧٨.
- `(?!(?:www\.)?instaparty\.eg)` lookahead excludes self-domain links from the external-link flag.
- The pattern set is small, auditable, and ADR-reviewable; ML is explicitly cut from Phase 1 per `09_Phasing_Plan.md` line 1726.

**Alternatives considered**:
- *Configurable patterns in `app_settings`* — rejected. Phase 2 work; admin-configurable rules need governance + change audit that's out of scope here.
- *Use `symfony/string` + Unicode normalizer* — rejected. The PHP `preg_replace` + `mb_*` functions are sufficient and avoid a new dependency.

---

## R5 — Idempotency strategy across the five Actions

**Decision**: Each Action checks for "already in target state" at the top inside `lockForUpdate()` and short-circuits with the existing record (no DB write, no audit row, no event). Specifically:

- **FreezeChatAction**: if `thread.status='locked' AND thread.frozen_at IS NOT NULL` → return `$thread` unchanged.
- **UnfreezeChatAction**: if `thread.status='open' AND thread.frozen_at IS NULL` → return `$thread` unchanged.
- **ResolveChatFlagAction**: if `flag.reviewed_at IS NOT NULL` → throw `ChatFlagAlreadyResolved` (409) — re-resolution must be a hard error so the admin sees they raced; not silent.
- **MarkOffPlatformContactAttemptAction**: per-(chat_message_log_id, flag_type) unique check; if a row exists for the same target, return the existing one without re-audit.
- **EscalateChatFlagToAdminInboxAction**: queries `admin_inbox_items` by `(source_type='ChatModerationFlag', source_id=$flag->id)` UNIQUE; reuses on hit.

**Rationale**:
- `admin_inbox_items` already has a UNIQUE index on `(source_type, source_id, admin_id)` from spec 019 — escalate idempotency rides on that index plus an existence check before insert.
- Freeze idempotency must be silent (no error) because admin UIs can fire double-submits; resolve idempotency must be loud because re-resolving means the workflow state diverged and the admin's mental model is wrong.

**Alternatives considered**:
- *Use the `idempotency_keys` table* — rejected. That table is reserved for payment-mutating endpoints (Constitution §VIII); using it here would dilute its semantics.

---

## R6 — Audit log writes — listener vs inline

**Decision**: A single `WriteChatModerationAuditListener` subscribed to all five domain events (`ChatThreadFrozen`, `ChatThreadUnfrozen`, `ChatModerationFlagResolved`, `OffPlatformContactMarked`, `ChatFlagEscalatedToInbox`). The listener writes one `audit_logs` row per event with `auditable_type` set to the subject class, `auditable_id` to its PK, `action` to a dot-namespaced string (`chat.frozen` / `chat.unfrozen` / `chat.flag.resolved` / `chat.flag.marked_off_platform` / `chat.flag.escalated`), and `changes` JSON carrying `{before, after}` snapshots plus the bilingual reason.

**Rationale**:
- Single listener keeps audit logic in one file, easy to test for the "exactly one audit row per action" invariant (SC-003).
- `audit_logs.action` follows the existing dot-namespace convention used by `WriteBookingStateTransitionListener` (`booking.state.changed`).
- Listener runs inside the event dispatch which is invoked from `DB::afterCommit` — so the audit row is only written if the transaction committed, preventing audit/state drift.

**Alternatives considered**:
- *Inline `DB::table('audit_logs')->insert(...)` inside each Action* — rejected. Duplicates the row shape across 5 Actions and makes the SC-003 invariant test harder to write.
- *Five separate listeners* — rejected. Unnecessary fan-out.

---

## R7 — Notification template registration

**Decision**: Register five new `notification_templates` rows via the existing `NotificationTemplate` seeder pattern (`event_key, channel, audience` UNIQUE):

| event_key | channels | audiences |
|---|---|---|
| `chat.thread_frozen` | `push`, `in_app` | `customer`, `vendor` |
| `chat.thread_unfrozen` | `push`, `in_app` | `customer`, `vendor` |
| `chat.flag_escalated` | `in_app` | `admin` |

Each template carries bilingual JSON for `subject` and `body` per `notification_templates` schema (locked in `11_DB_Schema.md` lines 1161–1177). The two Listener-fired notifications (`ChatThreadFrozen`, `ChatThreadUnfrozen`) dispatch through `NotificationDispatchAction` which already handles bilingual locale resolution at the Resource layer.

**Rationale**:
- Reuses the existing Phase 5.0 notification infrastructure end-to-end.
- Push + in-app (no SMS) keeps message volume reasonable for freeze events that should not interrupt a customer's sleep.
- `chat.flag_escalated` is admin-audience-only because the offender doesn't need to know the case got escalated — only that the chat got frozen, which `chat.thread_frozen` already conveys.

**Alternatives considered**:
- *SMS on freeze* — rejected. Spammy; Phase 1 SMS budget is reserved for booking-status changes (Tech Decisions §6).
- *Email on escalate to offender* — rejected. Phase 2 work for the user-warning system.

---

## R8 — Sub-status check on unfreeze (Vendor Journey rule)

**Decision**: `UnfreezeChatAction` performs a `lockForUpdate` join through `chat_threads → bookings → booking_vendors` to fetch the current `sub_status`. If the result is not in `('pending', 'modified')`, the Action throws a `ChatThreadUnfreezeForbidden` exception that maps to HTTP 409 with a bilingual message. The check is inside the transaction so the booking_vendor row cannot transition out from under it.

**Rationale**:
- `07_Vendor_Journey.md` line 198 makes restricted chat a function of the review window: the thread is only legitimately open while the vendor is still deciding. Unfreezing post-decision would re-open a channel that the lifecycle has already closed.
- `lockForUpdate` defends against the race where an admin clicks Unfreeze just as the booking transitions out of review.

**Alternatives considered**:
- *Allow unfreeze regardless and let the natural lifecycle close it again on next event* — rejected. Surfaces a transient open state that confuses the UI and could leak one message between admin click and lifecycle close.

---

## R9 — Detection job triggering

**Decision**: The Firestore listener that mirrors messages into `chat_message_log` dispatches `DetectSuspiciousMessageJob` on the `chat-moderation` queue after the INSERT commits. The job is idempotent per `(chat_message_log_id, flag_type)` via a unique-index check before insert into `chat_moderation_flags` (added to the migration). Retries on transient failures are safe.

**Rationale**:
- Decoupling detection from the listener INSERT keeps the listener path fast (it doesn't block the customer/vendor's next message ack).
- A dedicated queue (`chat-moderation`) lets Horizon tune concurrency separately from notification and booking queues.

**Alternatives considered**:
- *Run detection synchronously inside the listener* — rejected. Even 50ms p95 regex evaluation can spike under load; queueing is the standard pattern (`02_Tech_Decisions.md §5` says all non-trivial work goes to Redis).

---

## R10 — Permission model

**Decision**: Six new Spatie permissions in a `chat_moderation` group:

- `chat_moderation.view` — required for navigation + ListChatThreads page
- `chat_moderation.freeze`
- `chat_moderation.unfreeze`
- `chat_moderation.resolve_flag`
- `chat_moderation.mark_off_platform`
- `chat_moderation.escalate`

Seeded via `ChatModerationPermissionsSeeder` and assigned to the existing `admin` role on first run. `shield:generate --all` is rerun after the new Resources land so Shield knows about per-resource view permissions (`view_any_chat_thread`, etc.); those Shield permissions are scoped narrower than the action permissions and both must be granted for full access.

**Rationale**:
- Six permissions matches the five Actions + one view. A future Phase 2 might split per-team (off-platform team vs general moderation) — the existing structure makes that an additive change.
- Shield + Spatie running together is the established pattern in the Communication module (already used by `AdminInboxResource`).

**Alternatives considered**:
- *Single `chat_moderation.moderate` permission* — rejected. Loses the ability to delegate (e.g., a junior admin can resolve flags but not freeze).

---

## Open Questions

- None blocking. The detection-job sender path assumes the Firestore listener is in place; if it lags, the seeded fixtures in `tests/Feature/Modules/Communication/AdminChatModeration/*` cover the admin UI end-to-end without needing real Firestore traffic.
