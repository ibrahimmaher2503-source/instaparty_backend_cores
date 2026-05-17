# Quickstart: Admin Restricted Chat Moderation UI

**Feature**: `036-admin-chat-moderation`
**Branch**: `036-admin-chat-moderation`
**Phase**: 8.2 — Restricted Chat, Compliance & Off-Platform Prevention

This quickstart walks a developer through running the moderation surface end-to-end on a freshly-migrated dev environment.

---

## 1. Bring the schema up to date

```powershell
php artisan migrate
```

This applies the two new migrations:

- `2026_05_17_100001_create_chat_message_log_table.php`
- `2026_05_17_100002_create_chat_moderation_flags_table.php`

The existing `chat_threads` table is unchanged (the `frozen_at` / `frozen_by` columns were added in `2026_05_16_100004_add_frozen_to_chat_threads.php` already).

---

## 2. Seed permissions + a baseline admin user

```powershell
php artisan db:seed --class="App\\Modules\\Communication\\Database\\Seeders\\ChatModerationPermissionsSeeder"
php artisan db:seed --class=AdminUserSeeder
```

The first seeder is idempotent and creates the six `chat_moderation.*` permissions plus the `admin` role binding. The second creates a baseline admin so you can log into `/admin`.

---

## 3. Regenerate Shield permissions for the new Filament Resources

```powershell
php artisan shield:generate --all
```

This makes Shield aware of `view_any_chat_thread`, `view_chat_thread`, `view_any_chat_message_log`, `view_chat_message_log`, `view_any_chat_moderation_flag`, `view_chat_moderation_flag`. Combined with the Spatie permissions, each Action is gated by both the resource-view permission and the action permission.

---

## 4. Seed sample data

```powershell
php artisan db:seed --class="App\\Modules\\Communication\\Database\\Seeders\\ChatModerationDevelopmentSeeder"
```

This seeds:

- 10 `chat_threads` across all three product types (4 rental, 3 sale, 3 digital).
- 30 `chat_message_log` rows including 4 with phone numbers, 3 with emails, 2 with external links, and 1 manually-flagged off-platform attempt.
- The auto-detection job is dispatched synchronously in dev so the flag rows materialize before you visit the page.

(Skip this and create your own data via `tinker` if you prefer.)

---

## 5. Boot the dev stack

```powershell
php artisan serve
php artisan queue:work --queue=chat-moderation,notifications,default
php artisan reverb:start
```

The `queue:work` invocation listens on the `chat-moderation` queue used by `DetectSuspiciousMessageJob` and the `notifications` queue for `chat.thread_frozen` / `chat.thread_unfrozen` / `chat.flag_escalated` dispatches.

---

## 6. Walk the admin UI

1. Visit `http://localhost:8000/admin` and log in as the seeded admin.
2. **Navigation → Communication → Restricted Chat**: opens `ChatThreadResource` list. Two of the seeded threads should already show a "Frozen" pill (the seeder freezes them on purpose); three should show an open-flag count badge ≥ 1.
3. Click a thread with open flags. The Infolist renders:
   - **Booking Context** section (booking ref, customer, vendor, product type of first item).
   - **Read-Only Message View** — all `chat_message_log` rows for the thread in chronological order, with redacted bodies shown as `<message redacted by moderation>` in both locales.
   - **Audit Timeline** — every `audit_logs` row matching the thread's polymorphic identity.
4. On a flagged message row, click **Resolve Flag**. Fill `decision`, `note_en`, `note_ar` and submit. The flag disappears from the open list.
5. Click **Freeze Chat** at the top of the detail page. Fill bilingual reason + category and submit. The status pill flips to "Frozen".
6. Click **Unfreeze Chat**. If the booking_vendor sub-status is `pending` or `modified`, the action succeeds. Otherwise, a 409 toast appears with the bilingual error.
7. On the read-only message view, click any message → **Mark as Off-Platform Attempt**. Fill flag_type, bilingual reason, submit. A new flag row is created with `action_taken='block'`.
8. From the flag, click **Escalate to Admin Inbox**. Fill severity + bilingual summary. An `admin_inbox_items` row is created and routed; check **Navigation → Communication → Admin Inbox** to see the assigned item.

---

## 7. Verify the invariants

Run the Pest suite for this feature only:

```powershell
.\vendor\bin\pest --filter="AdminChatModeration"
```

Required passing test groups:

- `it freezes an open thread and returns 200`
- `it returns 200 unchanged when re-freezing` (idempotency)
- `it returns 409 unfreeze outside review window`
- `it never UPDATEs chat_message_log content fields` (invariant)
- `it has no edit route on ChatMessageLogResource` (FR-EXT-036-015 / SC-007)
- `it auto-flags Egyptian phone numbers including Eastern Arabic digits and spaced obfuscation`
- `it creates an inbox item on escalate and reuses on re-escalate`
- `it writes exactly one audit_logs row per Freeze/Unfreeze/Resolve/Mark/Escalate`

All eight must be green before the feature is mergeable.

---

## 8. ADR

Author `docs/adr/ADR-0014-chat-compliance-admin-oversight.md` and link it from `CLAUDE.md` ADR list before opening the PR. The ADR must cover:

- The three-layer append-only enforcement on `chat_message_log` (Application / Model / future-DB-trigger pass).
- The curated regex set and the Eastern-Arabic normalization.
- The decision to keep `chat_threads.status` ENUM unchanged and read `frozen_at IS NOT NULL` as the admin-freeze signal.
- The decision to defer ML detection per Phase 8.2 cut-list.

---

## 9. API documentation

After all five admin endpoints are implemented and tested:

```powershell
php artisan scribe:generate
```

Then append entries to `.specify/memory/api-registry.md`:

```markdown
| POST /admin/api/chat-threads/{id}/freeze | FreezeChatAction | Bilingual reason + category required; 422 missing AR; 403 sans chat_moderation.freeze; idempotent |
| POST /admin/api/chat-threads/{id}/unfreeze | UnfreezeChatAction | 409 outside review window |
| POST /admin/api/chat-moderation-flags/{id}/resolve | ResolveChatFlagAction | 4 decision enum; 409 already-resolved |
| POST /admin/api/chat-message-logs/{id}/mark-off-platform | MarkOffPlatformContactAttemptAction | Idempotent per (log, flag_type) |
| POST /admin/api/chat-moderation-flags/{id}/escalate | EscalateChatFlagToAdminInboxAction | Idempotent per inbox source |
```

Add Bruno collection under `docs/api/collections/admin-chat-moderation.bru` covering all five endpoints + the 422 / 403 / 404 / 409 negative cases with bilingual payloads.

---

## 10. Sanity checklist before opening PR

- [ ] `php artisan migrate:fresh --seed` succeeds on a clean DB.
- [ ] `php artisan shield:generate --all` runs without errors.
- [ ] `./vendor/bin/pint` is clean.
- [ ] `./vendor/bin/phpstan analyse` is clean for the Communication module.
- [ ] `./vendor/bin/pest --filter="AdminChatModeration"` is green.
- [ ] ADR-0014 is committed and linked from `CLAUDE.md`.
- [ ] Scribe regenerated and api-registry.md updated.
- [ ] Manual smoke test via `/admin` covers all five actions plus the read-only invariants (no Edit button anywhere on `ChatMessageLogResource` or `ChatThreadResource`).
