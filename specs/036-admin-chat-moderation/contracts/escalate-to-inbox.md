# Contract: Escalate Chat Flag to Admin Inbox

**Endpoint**: `POST /admin/api/chat-moderation-flags/{publicId}/escalate`
**Auth**: Sanctum + Filament admin guard
**Permission**: `chat_moderation.escalate`
**Idempotency**: Per-`(source_type='ChatModerationFlag', source_id, admin_id)` UNIQUE on `admin_inbox_items` — silent reuse.

---

## Request

`EscalateChatFlagRequest`:

| Field | Type | Required | Validation |
|---|---|---|---|
| `severity` | string | yes | `in:info,warning,critical` |
| `summary_en` | string | yes | `min:5,max:280` |
| `summary_ar` | string | yes | `min:5,max:280` |

```php
/**
 * @bodyParam severity string required Inbox severity. Example: warning
 * @bodyParam summary_en string required English summary. Example: "Vendor invited customer off-platform; thread already frozen."
 * @bodyParam summary_ar string required Arabic summary. Example: "دعا المورد العميل خارج المنصة، وتم تجميد المحادثة بالفعل."
 */
```

---

## Action

```php
final readonly class EscalateChatFlagToAdminInboxAction
{
    public function __construct(
        private ChatModerationRoutingHelper $router,
    ) {}

    public function execute(ChatModerationFlag $flag, EscalateChatFlagDTO $dto, User $actor): AdminInboxItem
    {
        return DB::transaction(function () use ($flag, $dto, $actor) {
            $assigneeId = $this->router->resolveAssignee($flag); // returns admin user id per Phase 6.1 routing rules

            $item = AdminInboxItem::query()
                ->where('source_type', 'App\\Modules\\Communication\\Domain\\Models\\ChatModerationFlag')
                ->where('source_id', $flag->id)
                ->where('admin_id', $assigneeId)
                ->lockForUpdate()
                ->first();

            if ($item !== null) {
                return $item;
            }

            $item = AdminInboxItem::create([
                'public_id'   => (string) Str::ulid(),
                'admin_id'    => $assigneeId,
                'source_type' => 'App\\Modules\\Communication\\Domain\\Models\\ChatModerationFlag',
                'source_id'   => $flag->id,
                'severity'    => $dto->severity,
                'title'       => ['en' => __('chat_moderation.escalation_title', [], 'en'), 'ar' => __('chat_moderation.escalation_title', [], 'ar')],
                'body'        => ['en' => $dto->summaryEn, 'ar' => $dto->summaryAr],
                'status'      => 'unread',
            ]);

            DB::afterCommit(fn () => event(new ChatFlagEscalatedToInbox($flag, $item, $actor)));

            return $item;
        });
    }
}
```

The `ChatModerationRoutingHelper` delegates to the existing Phase 6.1 `InboxRoutingRuleEngine` (already in `app/Modules/Communication/Infrastructure/Services/InboxRoutingRuleEngine.php`). No new routing rule shape is introduced.

---

## Response

| Status | Condition |
|---|---|
| 201 | New inbox item created |
| 200 | Existing inbox item returned (idempotent) |
| 401 / 403 / 404 / 422 | Standard |

---

## Side Effects

1. `admin_inbox_items` insert (or reuse).
2. `audit_logs` insert: `action='chat.flag.escalated'` with `admin_inbox_item_id` in `changes` JSON.
3. Domain event `ChatFlagEscalatedToInbox` after commit.
4. One `notification_dispatches` row to the assignee admin via `chat.flag_escalated` template (in-app, bilingual).

---

## Pest Coverage

- `it creates an admin_inbox_items row with item_type chat_violation linkage`
- `it returns the existing inbox item on re-escalate (idempotent)`
- `it routes the assignee via the existing InboxRoutingRuleEngine`
- `it sends an in-app notification to the assignee`
- `it returns 422 on missing bilingual summary or invalid severity`
- `it returns 403 without chat_moderation.escalate`
- `it writes exactly one audit_logs row per new escalation; zero on re-escalate`
