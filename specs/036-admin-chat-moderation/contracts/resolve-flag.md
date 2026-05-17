# Contract: Resolve Chat Moderation Flag

**Endpoint**: `POST /admin/api/chat-moderation-flags/{publicId}/resolve`
**Auth**: Sanctum + Filament admin guard
**Permission**: `chat_moderation.resolve_flag`
**Idempotency**: Loud — re-resolve returns 409.

---

## Request

`ResolveChatFlagRequest`:

| Field | Type | Required | Validation |
|---|---|---|---|
| `decision` | string | yes | `in:upheld_redact,upheld_warn,upheld_block,dismissed_false_positive` |
| `note_en` | string | yes | `min:5,max:500` |
| `note_ar` | string | yes | `min:5,max:500` |

```php
/**
 * @bodyParam decision string required Resolution decision. Example: upheld_redact
 * @bodyParam note_en string required English note. Example: "Confirmed phone number — message redacted."
 * @bodyParam note_ar string required Arabic note. Example: "تم تأكيد وجود رقم هاتف — تم إخفاء الرسالة."
 */
```

---

## Action

```php
final readonly class ResolveChatFlagAction
{
    public function execute(ChatModerationFlag $flag, ResolveChatFlagDTO $dto, User $actor): ChatModerationFlag
    {
        return DB::transaction(function () use ($flag, $dto, $actor) {
            $flag = ChatModerationFlag::query()->lockForUpdate()->findOrFail($flag->id);

            if ($flag->reviewed_at !== null) {
                throw new ChatFlagAlreadyResolved('Flag already resolved.');
            }

            $flag->fill([
                'reviewed_by'   => $actor->id,
                'reviewed_at'   => now(),
                'action_taken'  => $dto->decision->actionTaken(),
            ])->save();

            if ($dto->decision === ChatFlagResolution::UpheldRedact) {
                // Whitelisted UPDATE via fillable on ChatMessageLog — verified by NoChatContentMutationInvariantTest
                $flag->messageLog->update(['redacted' => true]);
            }

            DB::afterCommit(fn () => event(new ChatModerationFlagResolved($flag, $actor, $dto)));

            return $flag;
        });
    }
}
```

**Invariant**: The Action never writes to `chat_message_log.sender_id`, `firestore_message_id`, `message_kind`, or `detected_locale`. The mass assignment `update(['redacted' => true])` is only allowed because those are the only three fields in `$fillable`.

---

## Response

| Status | Condition |
|---|---|
| 200 | Resolved successfully |
| 401 | No Sanctum token |
| 403 | Permission missing |
| 404 | Flag not found |
| 409 | Already resolved (`reviewed_at IS NOT NULL`) — bilingual body |
| 422 | Missing bilingual note or invalid decision |

---

## Side Effects

1. `chat_moderation_flags`: `reviewed_by`, `reviewed_at`, `action_taken` set.
2. `chat_message_log.redacted=true` only when decision is `upheld_redact`.
3. `audit_logs` row with `action='chat.flag.resolved'`.
4. Domain event `ChatModerationFlagResolved` after commit.
5. No notification to customer/vendor (resolution is admin-internal).

---

## Pest Coverage

- `it resolves with upheld_redact and redacts the message`
- `it resolves with dismissed_false_positive and leaves redacted=false`
- `it resolves with upheld_warn / upheld_block without redacting`
- `it returns 409 when re-resolving`
- `it rejects 422 when note_ar is missing`
- `it rejects 403 without chat_moderation.resolve_flag`
- `it writes exactly one audit_logs row per resolution`
- `it never UPDATEs chat_message_log content fields (invariant test)`
