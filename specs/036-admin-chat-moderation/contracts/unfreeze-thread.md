# Contract: Unfreeze Chat Thread

**Endpoint**: `POST /admin/api/chat-threads/{publicId}/unfreeze`
**Auth**: Sanctum + Filament admin guard
**Permission**: `chat_moderation.unfreeze`
**Idempotency**: Silent on already-open threads — returns 200 with current state.

---

## Request

`UnfreezeChatRequest`:

| Field | Type | Required | Validation |
|---|---|---|---|
| `reason_en` | string | yes | `min:5,max:500` |
| `reason_ar` | string | yes | `min:5,max:500` |

```php
/**
 * @bodyParam reason_en string required English-language reason. Example: "Investigation cleared — content was vendor's own venue address, allowed."
 * @bodyParam reason_ar string required Arabic-language reason. Example: "تبين بعد التحقيق أن المحتوى عنوان قاعة المورد، وهو مسموح."
 */
```

---

## Action

```php
final readonly class UnfreezeChatAction
{
    public function execute(ChatThread $thread, UnfreezeChatDTO $dto, User $actor): ChatThread
    {
        return DB::transaction(function () use ($thread, $dto, $actor) {
            $thread = ChatThread::query()->lockForUpdate()->findOrFail($thread->id);

            if ($thread->status === 'open' && $thread->frozen_at === null) {
                return $thread;
            }

            $bookingVendor = $thread->bookingVendor()->lockForUpdate()->first();
            if (! $bookingVendor || ! in_array($bookingVendor->sub_status, ['pending', 'modified'], true)) {
                throw new ChatThreadUnfreezeForbidden(
                    'Cannot reopen chat after review window closed.',
                );
            }

            $thread->fill([
                'status'    => 'open',
                'frozen_at' => null,
                'frozen_by' => null,
            ])->save();

            DB::afterCommit(fn () => event(new ChatThreadUnfrozen($thread, $actor, $dto)));

            return $thread;
        });
    }
}
```

`ChatThreadUnfreezeForbidden` maps to a 409 response with bilingual body in the `Handler`.

---

## Response

| Status | Body |
|---|---|
| 200 | Same shape as freeze, with `frozen_at: null`, `status: open`. |
| 401 | No Sanctum token |
| 403 | Permission missing |
| 404 | Thread not found |
| 409 | `booking_vendor.sub_status NOT IN ('pending','modified')` — bilingual error message |
| 422 | Missing bilingual reason |

409 body:

```json
{
  "data": null,
  "meta": {},
  "errors": {
    "en": "Cannot reopen chat after review window closed.",
    "ar": "لا يمكن إعادة فتح المحادثة بعد انتهاء فترة المراجعة."
  }
}
```

---

## Side Effects

1. `chat_threads`: `status='open'`, `frozen_at=null`, `frozen_by=null`.
2. `audit_logs` insert: `action='chat.unfrozen'`.
3. Domain event `ChatThreadUnfrozen` after commit.
4. Two bilingual notification dispatches.

---

## Pest Coverage

- `it unfreezes a locked thread within the review window`
- `it returns 200 unchanged when re-unfreezing an open thread`
- `it returns 409 when booking_vendor.sub_status is outside ('pending','modified')`
- `it returns 422 when reason_en or reason_ar is missing`
- `it returns 403 without chat_moderation.unfreeze permission`
- `it writes exactly one audit_logs row per successful unfreeze`
