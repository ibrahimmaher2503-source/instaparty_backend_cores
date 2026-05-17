# ADR-0014 — Chat Compliance & Admin Oversight (Phase 8.2)

**Date:** 2026-05-17
**Status:** Accepted
**Author:** Ibrahim (Phase 8.2 — Restricted Chat, Compliance & Off-Platform Prevention)
**Branch:** 036-admin-chat-moderation

---

## Context

Phase 8.2 of `docs/specs/09_Phasing_Plan.md` (line 1681) introduces **Restricted Chat** between customers and vendors during the booking review window. The platform's revenue model depends on transactions completing on InstaParty — off-platform contact attempts (phone numbers, emails, external links, "WhatsApp me") are existential threats. Customers and vendors will try, intentionally or innocently, to share contact details inside the chat.

The platform mirrors all chat messages from Firestore into `chat_message_log` (an append-only audit table), and admin operators must be able to:

- Browse all restricted chat threads from a single Filament page
- Freeze a thread suspected of off-platform coordination (status → `locked` + `frozen_at`/`frozen_by`)
- Unfreeze a thread after investigation, but **only** while the booking_vendor sub_status is still `pending` or `modified`
- Resolve auto-detected suspicious-message flags with a bilingual decision
- Manually mark a confirmed off-platform contact attempt and escalate to the Phase 6.1 Admin Inbox

Three compliance invariants are non-negotiable:

1. **Append-only enforcement on `chat_message_log`** — no Action / Controller / Filament page / Console command may UPDATE the content fields (`sender_id`, `firestore_message_id`, `message_kind`, `detected_locale`, `chat_thread_id`, `created_at`) after insert. Only `flagged`, `flag_reason`, and `redacted` are mutable.
2. **No new `chat_threads.status` ENUM values** — the locked-schema doc (`11_DB_Schema.md`) anchors the enum at `('open','locked','closed')`. Admin freeze is signalled by `frozen_at IS NOT NULL`, not by a new ENUM case.
3. **Phase 8.2 cut-list excludes ML** — auto-detection uses a curated, ADR-reviewable regex set only. No NLP, no ML, no admin-configurable rule engine.

---

## Decision

Implement Phase 8.2 admin oversight as a moderation-focused extension of the existing `Communication` module:

1. Two new tables: `chat_message_log` (append-only) and `chat_moderation_flags` (status-mutable).
2. Five admin Actions: `FreezeChatAction`, `UnfreezeChatAction`, `ResolveChatFlagAction`, `MarkOffPlatformContactAttemptAction`, `EscalateChatFlagToAdminInboxAction`.
3. One auto-detection job: `DetectSuspiciousMessageJob` on the `chat-moderation` queue.
4. Three Filament Resources: `ChatThreadResource`, `ChatMessageLogResource`, `ChatModerationFlagResource` — all read-only with admin Actions on row/view pages.
5. Six new Spatie permissions: `chat_moderation.{view,freeze,unfreeze,resolve_flag,mark_off_platform,escalate}`.

---

## Three-Layer Append-Only Enforcement on `chat_message_log`

The append-only contract is enforced at **three independent layers** so that no single point of failure can violate it:

| Layer | Mechanism | Failure mode |
|---|---|---|
| **L1 — Migration** | `chat_message_log` migration declares **no** `updated_at` column; only `created_at` with `useCurrent()`. The schema itself signals append-only. | A developer who runs `php artisan make:migration` to "add updated_at" must explicitly add a column and override the model — flagged in PR review. |
| **L2 — Eloquent Model** | `ChatMessageLog::booted()` registers an `updating` listener. If `getDirty()` returns any keys outside `{flagged, flag_reason, redacted}`, the listener throws `\LogicException` with the offending column names. | Any developer using `->update(['sender_id' => ...])` or `->save()` after dirtying a content column gets a runtime exception in dev and in tests. |
| **L3 — Invariant Pest Test** | `NoChatContentMutationInvariantTest` enumerates every Eloquent `update()`/`save()` call against `ChatMessageLog` across the codebase and asserts the changed-column set is a subset of `{flagged, flag_reason, redacted}`. Also invokes the updating listener directly with a forbidden column to assert it throws. | If a future developer adds a content-mutating Action, the test fails in CI before merge. |

A DB trigger is intentionally **not** added in Phase 1. `02_Tech_Decisions.md §4` reserves DB triggers for `wallet_ledger` only. Adding another would burn capacity better spent on the Phase 8.2 cut-list. The three-layer guard is "trust but verify" — verified by the Pest invariant test.

---

## Curated Regex Set + Eastern-Arabic Digit Normalization

Auto-detection runs inside `MessagePatternDetector::detect(string $body): array`. Before matching, the body is normalized:

- Eastern Arabic digits (`٠١٢٣٤٥٦٧٨٩`) → Latin digits (`0123456789`)
- Zero-width joiners (ZWNJ `‌`, ZWJ `‍`) and non-breaking spaces (NBSP ` `) are stripped
- Consecutive whitespace collapsed to a single space

Then four regex patterns run:

| flag_type | flag_reason | Pattern | Rationale |
|---|---|---|---|
| `phone` | `phone_pattern` | `/(?:\+?20|0)?\s*1\s*[0125]\s*\d(?:[\s.\-]*\d){7}/u` | Egyptian carriers (010 / 011 / 012 / 015) tolerating spaces, dashes, dots |
| `phone` | `phone_pattern` | `/\+\d{10,15}/u` | International E.164 fallback |
| `email` | `email_pattern` | `/[\w.+-]+@[\w-]+\.[\w.-]+/iu` | Standard email |
| `external_link` | `external_link` | `/https?:\/\/(?!(?:www\.)?instaparty\.eg)[^\s]+/iu` | External URLs only; `instaparty.eg` excluded |

The `MarkOffPlatformContactAttemptAction` provides a manual flag path for cases the regex misses (e.g., "DM me on Instagram", "find me at @handle" — these are too varied to encode safely).

**Why no ML in Phase 1:**

- `09_Phasing_Plan.md` line 1726 explicitly cuts ML from Phase 8.2.
- Regex is ADR-reviewable; an ML model is not, and a black-box flagging system would require an explainability layer we don't have.
- Curated patterns are sufficient for the high-volume Egyptian carrier case, which is ~80% of expected violations.

---

## `chat_threads.status` ENUM Stays Unchanged — `frozen_at` Is the Admin-Freeze Signal

The existing enum `chat_threads.status ENUM('open','locked','closed')` is locked by `11_DB_Schema.md`. Adding a `frozen` value would require a schema-doc revision and a destructive enum migration. Instead:

- Admin-initiated freeze flips `status → 'locked'` AND sets `frozen_at = now()`, `frozen_by = admin.id`.
- Lifecycle-driven lock (booking_vendor sub_status leaves `pending`/`modified`) flips `status → 'locked'` with `frozen_at IS NULL`.
- The admin UI renders a "Frozen by admin" pill when `frozen_at IS NOT NULL`, and "Locked" otherwise.

This preserves enum semantics and gives operators a clear signal of intent.

---

## Phase 8.2 Cut-List (Explicitly Out of Scope)

The following are deferred to Phase 2 (or later) and **must not** be built as part of this feature:

- ML-based intent detection or sentiment analysis
- Admin-configurable regex/rule storage in `app_settings`
- Automatic message redaction without admin review (`upheld_redact` requires explicit admin decision)
- Customer/vendor warning system (notifications fire only to the affected parties via existing templates; no escalating warning levels)
- SMS notifications on freeze (push + in-app only; SMS budget reserved for booking-status changes per Tech Decisions §6)
- Per-team permission splits (e.g., off-platform team vs general moderation) — six permissions is the Phase 1 scope; splits become an additive change
- DB triggers on `chat_message_log` — covered by L1+L2+L3 instead

---

## Consequences

- Two new tables under Communication module migrations folder.
- One new Pest invariant test (`NoChatContentMutationInvariantTest`) — enforced in CI.
- Six new permissions seeded idempotently and assigned to the `admin` role.
- The `chat-moderation` queue is added to Horizon supervisor config.
- `MessagePatternDetector` is the single source of truth for detection patterns — any future pattern change requires an ADR amendment.
- All Filament moderation surfaces are read-only with explicit Action wiring. No `EditAction`/`DeleteAction`/`CreateAction` on any chat resource.

---

## Rejected Alternatives

| Alternative | Rejected because |
|---|---|
| Add `frozen` ENUM value to `chat_threads.status` | Schema-doc drift; destructive enum migration |
| DB trigger as sole append-only enforcement | Hides invariant from PHP developers; only `wallet_ledger` is trigger-protected in this codebase |
| ML / NLP-based detection | Explicitly cut from Phase 8.2; black-box flagging is not ADR-reviewable |
| Admin-configurable regex in `app_settings` | Phase 2 work; requires governance + change audit out of scope here |
| Use `idempotency_keys` for Action idempotency | Reserved for payment-mutating endpoints (Constitution §VIII); per-row UNIQUE indexes suffice here |
| Five separate audit listeners | Unnecessary fan-out; one listener subscribed to six events is simpler to test |

---

## Internal Decisions (spec 037)

The following decisions were added when spec 037 (Vendor Booking Chat Panel) was implemented on the same branch.

### ID-037-1 — Vendor-side send path reuses `MessagePatternDetector`

The vendor-side `SendVendorChatMessageAction` uses the existing
`MessagePatternDetector` (at `Infrastructure/Services/MessagePatternDetector.php`)
as the primary compliance gate on every vendor send. The admin moderation job
(`DetectSuspiciousMessageJob`) uses the same service as a secondary sweep after
the message is delivered. `FirestoreChatGateway::sendMessage()` is added to the
contract; the stub returns a fake ID. The gateway call fires in `DB::afterCommit()`
so a gateway failure does not orphan a `chat_message_log` row — the transaction
rolled back before the callback fires.

### ID-037-2 — Short-lived Redis dedup for vendor send retries

A 10-second Redis dedup key (`chat_dedup:{threadId}:{vendorId}:{md5(body)}`) protects
against Livewire double-submit and mobile-reconnect retries on the vendor send path.
The full `idempotency_keys` table pattern (24-hour TTL, per-scope UNIQUE index) is
reserved for payment-mutating endpoints per Constitution §VIII. This deviation is
acceptable because vendor chat retries within a 10-second window are self-correcting
at the UI layer (Livewire `wire:loading` prevents duplicate submissions), and blocked
messages each get their own `chat_moderation_flags` row regardless of the dedup state.

---

## References

- `docs/specs/09_Phasing_Plan.md` — Phase 8.2 line 1681, cut-list line 1726
- `docs/specs/11_DB_Schema.md` — `chat_message_log` §1131, `chat_moderation_flags` §1148
- `docs/specs/07_Vendor_Journey.md` — Restricted chat review-window rule line 198
- `.claude/rules/schema-cheatsheet.md` — Append-only tables list
- `specs/036-admin-chat-moderation/` — Full feature spec, data-model, research, contracts, quickstart
- `specs/037-vendor-booking-chat-panel/` — Vendor chat panel spec, adds Internal Decisions ID-037-1 and ID-037-2
