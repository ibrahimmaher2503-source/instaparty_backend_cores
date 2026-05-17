# Contract: Freeze Chat Thread

**Endpoint**: `POST /admin/api/chat-threads/{publicId}/freeze`
**Auth**: Sanctum + Filament admin guard
**Permission**: `chat_moderation.freeze`
**Idempotency**: Silent on already-frozen threads — returns 200 with current state.

---

## Request

`FreezeChatRequest` (Form Request):

| Field | Type | Required | Validation |
|---|---|---|---|
| `reason_en` | string | yes | `min:5,max:500` |
| `reason_ar` | string | yes | `min:5,max:500` |
| `category` | string | yes | `in:off_platform_contact,policy_violation,harassment,other` |

```php
/**
 * @bodyParam reason_en string required English-language reason. Example: "Suspected phone number exchange to evade booking."
 * @bodyParam reason_ar string required Arabic-language reason. Example: "اشتباه في تبادل أرقام هواتف للالتفاف على الحجز."
 * @bodyParam category string required Freeze category. Example: off_platform_contact
 */
```

Missing either locale → 422 with field-level errors.
Missing/invalid `category` → 422.
Missing permission → 403.

---

## Action

```php
final readonly class FreezeChatAction
{
    public function __construct(
        private Dispatcher $events,
    ) {}

    public function execute(ChatThread $thread, FreezeChatDTO $dto, User $actor): ChatThread
    {
        return DB::transaction(function () use ($thread, $dto, $actor) {
            $thread = ChatThread::query()->lockForUpdate()->findOrFail($thread->id);

            // Idempotent short-circuit
            if ($thread->status === 'locked' && $thread->frozen_at !== null) {
                return $thread;
            }

            $thread->fill([
                'status'     => 'locked',
                'frozen_at'  => now(),
                'frozen_by'  => $actor->id,
            ])->save();

            DB::afterCommit(fn () => $this->events->dispatch(
                new ChatThreadFrozen($thread, $actor, $dto),
            ));

            return $thread;
        });
    }
}
```

---

## Response

```php
/**
 * @response 200 {
 *   "data": {
 *     "public_id": "01H...",
 *     "status": "locked",
 *     "frozen_at": "2026-05-16T14:33:21Z",
 *     "frozen_by": { "id": 42, "name": "Ibrahim" },
 *     "reason": { "en": "Suspected phone number exchange to evade booking.", "ar": "اشتباه في تبادل أرقام هواتف للالتفاف على الحجز." },
 *     "category": "off_platform_contact"
 *   },
 *   "meta": {},
 *   "errors": null
 * }
 */
```

| Status | Condition |
|---|---|
| 200 | Frozen (new or already-frozen — idempotent) |
| 401 | No Sanctum token |
| 403 | Permission `chat_moderation.freeze` missing |
| 404 | Thread `public_id` not found |
| 422 | Missing EN or AR reason, or invalid category |

---

## Side Effects

1. `chat_threads` row: `status='locked'`, `frozen_at=now()`, `frozen_by=actor.id`.
2. `audit_logs` insert: `action='chat.frozen'`, `auditable=ChatThread`, `changes` JSON with bilingual reason + category.
3. Domain event `ChatThreadFrozen` dispatched after commit.
4. Two `notification_dispatches` rows queued via `chat.thread_frozen` templates — one for the customer, one for the vendor primary user. Both bilingual.

---

## Pest Coverage

- `it freezes an open thread and returns 200`
- `it returns 200 unchanged when re-freezing a frozen thread (idempotent, no duplicate audit row)`
- `it rejects 422 when reason_ar is missing`
- `it rejects 422 when category is invalid`
- `it rejects 403 when user lacks chat_moderation.freeze`
- `it writes exactly one audit_logs row per successful freeze`
- `it enqueues bilingual notifications for both customer and vendor`
- `it fires ChatThreadFrozen event exactly once via DB::afterCommit`
