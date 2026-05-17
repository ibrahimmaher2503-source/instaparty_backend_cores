# Domain Events Contract

**Feature**: `036-admin-chat-moderation`

All events fire **after** `DB::transaction` commits (Constitution §VI). All events implement `App\\Modules\\Shared\\Domain\\Events\\AuditableEvent` so a single `WriteChatModerationAuditListener` writes the `audit_logs` row uniformly.

---

## `ChatThreadFrozen`

```php
final readonly class ChatThreadFrozen implements AuditableEvent
{
    public function __construct(
        public ChatThread $thread,
        public User $actor,
        public FreezeChatDTO $dto,
    ) {}

    public function auditable(): Model { return $this->thread; }
    public function actor(): User { return $this->actor; }
    public function action(): string { return 'chat.frozen'; }
    public function changes(): array {
        return [
            'before' => ['status' => 'open', 'frozen_at' => null, 'frozen_by' => null],
            'after'  => ['status' => 'locked', 'frozen_at' => $this->thread->frozen_at, 'frozen_by' => $this->thread->frozen_by],
            'reason_en' => $this->dto->reasonEn,
            'reason_ar' => $this->dto->reasonAr,
            'category'  => $this->dto->category->value,
        ];
    }
}
```

**Subscribers**:
- `WriteChatModerationAuditListener` → writes `audit_logs` row.
- `DispatchChatThreadFrozenNotificationsListener` → enqueues bilingual notifications for customer + vendor.

---

## `ChatThreadUnfrozen`

Mirror of `ChatThreadFrozen` with `action='chat.unfrozen'`. Subscribers same shape.

---

## `ChatMessageFlagged`

```php
final readonly class ChatMessageFlagged implements AuditableEvent
{
    public function __construct(
        public ChatMessageLog $log,
        public ChatModerationFlag $flag,
    ) {}

    public function auditable(): Model { return $this->flag; }
    public function actor(): ?User { return null; } // system-initiated
    public function action(): string { return 'chat.message.flagged'; }
    public function changes(): array {
        return ['flag_type' => $this->flag->flag_type->value, 'matched_pattern' => $this->flag->matched_pattern];
    }
}
```

**Subscribers**:
- `WriteChatModerationAuditListener` → audit row with `user_id=NULL` (system actor).

---

## `ChatModerationFlagResolved`

```php
final readonly class ChatModerationFlagResolved implements AuditableEvent
{
    public function __construct(
        public ChatModerationFlag $flag,
        public User $actor,
        public ResolveChatFlagDTO $dto,
    ) {}

    public function action(): string { return 'chat.flag.resolved'; }
    public function changes(): array {
        return [
            'decision' => $this->dto->decision->value,
            'note_en'  => $this->dto->noteEn,
            'note_ar'  => $this->dto->noteAr,
            'action_taken' => $this->flag->action_taken->value,
            'redacted' => $this->flag->messageLog->redacted,
        ];
    }
}
```

**Subscribers**:
- `WriteChatModerationAuditListener`.

---

## `OffPlatformContactMarked`

```php
final readonly class OffPlatformContactMarked implements AuditableEvent
{
    public function __construct(
        public ChatModerationFlag $flag,
        public User $actor,
        public MarkOffPlatformContactDTO $dto,
    ) {}

    public function action(): string { return 'chat.flag.marked_off_platform'; }
}
```

---

## `ChatFlagEscalatedToInbox`

```php
final readonly class ChatFlagEscalatedToInbox implements AuditableEvent
{
    public function __construct(
        public ChatModerationFlag $flag,
        public AdminInboxItem $inboxItem,
        public User $actor,
    ) {}

    public function action(): string { return 'chat.flag.escalated'; }
    public function changes(): array {
        return ['admin_inbox_item_id' => $this->inboxItem->id, 'severity' => $this->inboxItem->severity];
    }
}
```

**Subscribers**:
- `WriteChatModerationAuditListener`.
- `DispatchChatFlagEscalatedNotificationsListener` — in-app bilingual notification to assignee admin.

---

## Listener Wiring

`CommunicationServiceProvider::boot()` adds:

```php
Event::listen(ChatThreadFrozen::class, WriteChatModerationAuditListener::class);
Event::listen(ChatThreadFrozen::class, DispatchChatThreadFrozenNotificationsListener::class);
Event::listen(ChatThreadUnfrozen::class, WriteChatModerationAuditListener::class);
Event::listen(ChatThreadUnfrozen::class, DispatchChatThreadUnfrozenNotificationsListener::class);
Event::listen(ChatMessageFlagged::class, WriteChatModerationAuditListener::class);
Event::listen(ChatModerationFlagResolved::class, WriteChatModerationAuditListener::class);
Event::listen(OffPlatformContactMarked::class, WriteChatModerationAuditListener::class);
Event::listen(ChatFlagEscalatedToInbox::class, WriteChatModerationAuditListener::class);
Event::listen(ChatFlagEscalatedToInbox::class, DispatchChatFlagEscalatedNotificationsListener::class);
```

All notification listeners implement `ShouldQueue` on the `notifications` queue.

---

## Test Invariants

- For each Action's happy path, Pest asserts:
  - `Event::assertDispatched($EventClass, fn($e) => $e->auditable->is($subject))` fires exactly once.
  - `audit_logs` count delta is `+1` per fired event (the `ChatModerationFlagResolved` test asserts the count delta is `+1`, not `+2`, even though redaction touches the message log — because only one event fires).
  - For idempotent re-runs, `Event::assertNotDispatched($EventClass)`.
