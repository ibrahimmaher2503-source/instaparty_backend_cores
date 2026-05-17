# Contract: Mark Off-Platform Contact Attempt

**Endpoint**: `POST /admin/api/chat-message-logs/{publicId}/mark-off-platform`
**Auth**: Sanctum + Filament admin guard
**Permission**: `chat_moderation.mark_off_platform`
**Idempotency**: Per-`(chat_message_log_id, flag_type)` UNIQUE — silent reuse.

---

## Request

`MarkOffPlatformContactRequest`:

| Field | Type | Required | Validation |
|---|---|---|---|
| `flag_type` | string | yes | `in:phone,email,external_link,other` |
| `reason_en` | string | yes | `min:5,max:500` |
| `reason_ar` | string | yes | `min:5,max:500` |

```php
/**
 * @bodyParam flag_type string required Detected violation category. Example: external_link
 * @bodyParam reason_en string required English-language reason. Example: "Vendor invited customer to direct-message on Instagram."
 * @bodyParam reason_ar string required Arabic-language reason. Example: "دعا المورد العميل إلى المراسلة المباشرة على إنستجرام."
 */
```

`flag_type='profanity'` is NOT a valid manual mark — profanity goes through the (Phase 2) profanity filter; manual marks are for off-platform contact attempts only.

---

## Action

```php
final readonly class MarkOffPlatformContactAttemptAction
{
    public function execute(ChatMessageLog $log, MarkOffPlatformContactDTO $dto, User $actor): ChatModerationFlag
    {
        return DB::transaction(function () use ($log, $dto, $actor) {
            $log = ChatMessageLog::query()->lockForUpdate()->findOrFail($log->id);

            $flag = ChatModerationFlag::query()
                ->where('chat_message_log_id', $log->id)
                ->where('flag_type', $dto->flagType->value)
                ->lockForUpdate()
                ->first();

            if ($flag !== null) {
                return $flag; // Idempotent
            }

            $flag = ChatModerationFlag::create([
                'chat_message_log_id' => $log->id,
                'flag_type'           => $dto->flagType,
                'matched_pattern'     => null,
                'action_taken'        => ChatFlagAction::Block,
            ]);

            $log->update([
                'flagged'     => true,
                'flag_reason' => 'manual',
            ]);

            DB::afterCommit(fn () => event(new OffPlatformContactMarked($flag, $actor, $dto)));

            return $flag;
        });
    }
}
```

---

## Response

| Status | Condition |
|---|---|
| 201 | Newly created flag |
| 200 | Existing flag reused (idempotent) |
| 401 / 403 / 404 / 422 | Standard |

---

## Side Effects

1. `chat_moderation_flags` insert (or reuse).
2. `chat_message_log.flagged=true`, `flag_reason='manual'`.
3. `audit_logs` insert: `action='chat.flag.marked_off_platform'`.
4. Domain event `OffPlatformContactMarked` after commit.

---

## Pest Coverage

- `it inserts a new flag with action_taken=block and flag_type from input`
- `it sets the underlying message log flagged=true with flag_reason=manual`
- `it returns the existing flag when (log, flag_type) already exists`
- `it returns 422 on invalid flag_type`
- `it returns 422 on missing bilingual reason`
- `it returns 403 without chat_moderation.mark_off_platform`
- `it never mutates chat_message_log content fields (invariant test)`
