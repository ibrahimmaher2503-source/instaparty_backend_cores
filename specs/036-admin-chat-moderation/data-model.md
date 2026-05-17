# Phase 1 Data Model: Admin Restricted Chat Moderation UI

**Feature**: `036-admin-chat-moderation`
**Date**: 2026-05-16

---

## Tables Touched

| Table | Status | Notes |
|---|---|---|
| `chat_threads` | EXISTING — no schema change | Reuses `status`, `frozen_at`, `frozen_by` (already in migrations) |
| `chat_message_log` | NEW migration (table is locked in `11_DB_Schema.md` §1131) | Append-only; only `flagged`, `flag_reason`, `redacted` may UPDATE post-insert |
| `chat_moderation_flags` | NEW migration (table is locked in `11_DB_Schema.md` §1148) | One row per detected or admin-flagged issue |
| `audit_logs` | EXISTING — INSERTs from `WriteChatModerationAuditListener` | One row per Freeze/Unfreeze/Resolve/Mark/Escalate |
| `admin_inbox_items` | EXISTING (Phase 6.1) — INSERTs from `EscalateChatFlagToAdminInboxAction` | `source_type='ChatModerationFlag'` |
| `notification_dispatches` | EXISTING — INSERTs via existing `NotificationDispatchAction` | Bilingual freeze/unfreeze/escalate notifications |

No new ENUM values are added to any existing column.

---

## Migration 1 — `2026_05_17_100001_create_chat_message_log_table.php`

Column shapes follow `11_DB_Schema.md` §`chat_message_log` (append-only mirror) lines 1131–1146.

```php
Schema::create('chat_message_log', function (Blueprint $table): void {
    $table->charset = 'utf8mb4';
    $table->collation = 'utf8mb4_unicode_ci';

    $table->bigIncrements('id');
    $table->foreignId('chat_thread_id')->constrained('chat_threads')->restrictOnDelete();
    $table->string('firestore_message_id', 120);
    $table->foreignId('sender_id')->constrained('users')->restrictOnDelete();
    $table->enum('message_kind', ['text', 'image', 'voice', 'system']);
    $table->enum('detected_locale', ['ar', 'en', 'mixed'])->nullable();
    $table->boolean('flagged')->default(false);
    $table->string('flag_reason', 80)->nullable();      // phone_pattern, email_pattern, manual, external_link, post_lock
    $table->boolean('redacted')->default(false);
    $table->timestamp('created_at')->useCurrent();

    $table->unique(['chat_thread_id', 'firestore_message_id'], 'cml_thread_firestore_unique');
    $table->index(['chat_thread_id', 'created_at'], 'cml_thread_created_idx');
    $table->index(['flagged'], 'cml_flagged_idx');
});
```

**Append-only contract**: no `updated_at`. The migration includes a comment block referencing ADR-0014 and the application-layer / model-layer enforcement of the no-content-mutation invariant.

---

## Migration 2 — `2026_05_17_100002_create_chat_moderation_flags_table.php`

Column shapes follow `11_DB_Schema.md` §`chat_moderation_flags` lines 1148–1159.

```php
Schema::create('chat_moderation_flags', function (Blueprint $table): void {
    $table->charset = 'utf8mb4';
    $table->collation = 'utf8mb4_unicode_ci';

    $table->bigIncrements('id');
    $table->foreignId('chat_message_log_id')->constrained('chat_message_log')->restrictOnDelete();
    $table->enum('flag_type', ['phone', 'email', 'profanity', 'external_link', 'other']);
    $table->string('matched_pattern', 255)->nullable();
    $table->enum('action_taken', ['redact', 'warn', 'block', 'none'])->default('warn');
    $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('reviewed_at')->nullable();
    $table->timestamp('created_at')->useCurrent();

    $table->unique(['chat_message_log_id', 'flag_type'], 'cmf_log_type_unique');
    $table->index(['reviewed_at'], 'cmf_reviewed_idx');
    $table->index(['flag_type', 'reviewed_at'], 'cmf_type_reviewed_idx');
});
```

The `(chat_message_log_id, flag_type)` UNIQUE index implements R5 idempotency for the detection job and `MarkOffPlatformContactAttemptAction`.

---

## Eloquent Models

### `ChatMessageLog` (NEW)

```php
namespace App\Modules\Communication\Domain\Models;

class ChatMessageLog extends Model
{
    protected $table = 'chat_message_log';
    public $timestamps = false;

    // Only these three may be mass-assigned after creation.
    // chat_thread_id, sender_id, firestore_message_id, message_kind, detected_locale
    // are INSERT-only and must be passed via ->create() with the full payload.
    protected $fillable = [
        'flagged',
        'flag_reason',
        'redacted',
    ];

    protected $casts = [
        'flagged'    => 'boolean',
        'redacted'   => 'boolean',
        'created_at' => 'datetime',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ChatThread::class, 'chat_thread_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'sender_id');
    }

    public function flags(): HasMany
    {
        return $this->hasMany(ChatModerationFlag::class);
    }

    protected static function booted(): void
    {
        static::updating(function (self $log): void {
            $forbidden = array_diff(
                array_keys($log->getDirty()),
                ['flagged', 'flag_reason', 'redacted'],
            );
            if (! empty($forbidden)) {
                throw new \LogicException(
                    'chat_message_log content fields are append-only. Attempted to update: '
                    . implode(', ', $forbidden)
                );
            }
        });
    }
}
```

The `updating` listener implements R3 layer 2; the unit test `NoChatContentMutationInvariantTest` verifies it throws.

### `ChatModerationFlag` (NEW)

```php
class ChatModerationFlag extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'chat_message_log_id',
        'flag_type',
        'matched_pattern',
        'action_taken',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'flag_type'   => ChatFlagType::class,
        'action_taken'=> ChatFlagAction::class,
        'reviewed_at' => 'datetime',
        'created_at'  => 'datetime',
    ];

    public function messageLog(): BelongsTo
    {
        return $this->belongsTo(ChatMessageLog::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'reviewed_by');
    }

    public function thread(): HasOneThrough
    {
        return $this->hasOneThrough(
            ChatThread::class,
            ChatMessageLog::class,
            'id',
            'id',
            'chat_message_log_id',
            'chat_thread_id',
        );
    }
}
```

### `ChatThread` (EXTEND)

Add these relations / scopes to the existing model:

```php
public function messages(): HasMany
{
    return $this->hasMany(ChatMessageLog::class)->orderBy('created_at');
}

public function unresolvedFlags(): HasManyThrough
{
    return $this->hasManyThrough(
        ChatModerationFlag::class,
        ChatMessageLog::class,
        'chat_thread_id',
        'chat_message_log_id',
    )->whereNull('reviewed_at');
}

public function bookingVendor(): HasOneThrough
{
    return $this->hasOneThrough(
        \App\Modules\Booking\Domain\Models\BookingVendor::class,
        \App\Modules\Booking\Domain\Models\Booking::class,
        'id',
        'booking_id',
        'booking_id',
        'id',
    )->where('vendor_profile_id', $this->vendor_profile_id);
}

public function scopeFrozen(Builder $q): Builder
{
    return $q->whereNotNull('frozen_at');
}

public function scopeWithOpenFlags(Builder $q): Builder
{
    return $q->whereHas('unresolvedFlags');
}
```

No column additions; the model edit is relations + scopes only.

---

## Domain Enums

```php
namespace App\Modules\Communication\Domain\Enums;

enum ChatFreezeCategory: string {
    case OffPlatformContact = 'off_platform_contact';
    case PolicyViolation    = 'policy_violation';
    case Harassment         = 'harassment';
    case Other              = 'other';
}

enum ChatFlagType: string {
    case Phone        = 'phone';
    case Email        = 'email';
    case Profanity    = 'profanity';
    case ExternalLink = 'external_link';
    case Other        = 'other';
}

enum ChatFlagAction: string {
    case Redact = 'redact';
    case Warn   = 'warn';
    case Block  = 'block';
    case None   = 'none';
}

enum ChatFlagResolution: string {
    case UpheldRedact         = 'upheld_redact';
    case UpheldWarn           = 'upheld_warn';
    case UpheldBlock          = 'upheld_block';
    case DismissedFalsePositive = 'dismissed_false_positive';

    public function actionTaken(): ChatFlagAction {
        return match ($this) {
            self::UpheldRedact          => ChatFlagAction::Redact,
            self::UpheldWarn            => ChatFlagAction::Warn,
            self::UpheldBlock           => ChatFlagAction::Block,
            self::DismissedFalsePositive => ChatFlagAction::None,
        };
    }
}

enum ChatMessageFlagReason: string {
    case PhonePattern  = 'phone_pattern';
    case EmailPattern  = 'email_pattern';
    case ExternalLink  = 'external_link';
    case Manual        = 'manual';
    case PostLock      = 'post_lock';
}
```

---

## DTOs

```php
final readonly class FreezeChatDTO
{
    public function __construct(
        public string $reasonEn,
        public string $reasonAr,
        public ChatFreezeCategory $category,
    ) {}
}

final readonly class UnfreezeChatDTO
{
    public function __construct(
        public string $reasonEn,
        public string $reasonAr,
    ) {}
}

final readonly class ResolveChatFlagDTO
{
    public function __construct(
        public ChatFlagResolution $decision,
        public string $noteEn,
        public string $noteAr,
    ) {}
}

final readonly class MarkOffPlatformContactDTO
{
    public function __construct(
        public int $chatMessageLogId,
        public ChatFlagType $flagType,
        public string $reasonEn,
        public string $reasonAr,
    ) {}
}

final readonly class EscalateChatFlagDTO
{
    public function __construct(
        public int $chatModerationFlagId,
        public string $severity, // 'info' | 'warning' | 'critical'
        public string $summaryEn,
        public string $summaryAr,
    ) {}
}
```

---

## Domain Events

| Event | Payload | Fired by |
|---|---|---|
| `ChatThreadFrozen` | `ChatThread`, `User $actor`, `FreezeChatDTO` | `FreezeChatAction` (afterCommit) |
| `ChatThreadUnfrozen` | `ChatThread`, `User $actor`, `UnfreezeChatDTO` | `UnfreezeChatAction` (afterCommit) |
| `ChatMessageFlagged` | `ChatMessageLog`, `ChatModerationFlag` | `DetectSuspiciousMessageJob` (afterCommit) |
| `ChatModerationFlagResolved` | `ChatModerationFlag`, `User $actor`, `ResolveChatFlagDTO` | `ResolveChatFlagAction` (afterCommit) |
| `OffPlatformContactMarked` | `ChatModerationFlag`, `User $actor`, `MarkOffPlatformContactDTO` | `MarkOffPlatformContactAttemptAction` (afterCommit) |
| `ChatFlagEscalatedToInbox` | `ChatModerationFlag`, `AdminInboxItem`, `User $actor` | `EscalateChatFlagToAdminInboxAction` (afterCommit) |

All events implement `App\\Modules\\Shared\\Domain\\Events\\AuditableEvent` so `WriteChatModerationAuditListener` can extract a uniform `{actor_id, auditable, action, changes}` shape.

---

## Audit Log Row Shapes

| Action | `auditable_type` | `auditable_id` | `action` | `changes` JSON |
|---|---|---|---|---|
| Freeze | `App\\Modules\\Communication\\Domain\\Models\\ChatThread` | thread.id | `chat.frozen` | `{ "before": {"status":"open","frozen_at":null}, "after": {"status":"locked","frozen_at":"...","frozen_by":42}, "reason_en":"...","reason_ar":"...","category":"off_platform_contact" }` |
| Unfreeze | `...\\ChatThread` | thread.id | `chat.unfrozen` | `{ "before": {...frozen...}, "after": {...open...}, "reason_en":"...","reason_ar":"..." }` |
| Resolve | `...\\ChatModerationFlag` | flag.id | `chat.flag.resolved` | `{ "before": {"reviewed_at":null,"action_taken":"warn"}, "after": {"reviewed_at":"...","reviewed_by":42,"action_taken":"redact"}, "decision":"upheld_redact","note_en":"...","note_ar":"..." }` |
| Mark off-platform | `...\\ChatModerationFlag` | flag.id | `chat.flag.marked_off_platform` | `{ "after": {flag_row}, "reason_en":"...","reason_ar":"..." }` |
| Escalate | `...\\ChatModerationFlag` | flag.id | `chat.flag.escalated` | `{ "admin_inbox_item_id": 7, "severity":"warning", "summary_en":"...","summary_ar":"..." }` |

---

## Permissions Seeded

| Permission | Guard | Assigned to |
|---|---|---|
| `chat_moderation.view` | `web` | `admin` role |
| `chat_moderation.freeze` | `web` | `admin` |
| `chat_moderation.unfreeze` | `web` | `admin` |
| `chat_moderation.resolve_flag` | `web` | `admin` |
| `chat_moderation.mark_off_platform` | `web` | `admin` |
| `chat_moderation.escalate` | `web` | `admin` |

Seeded by `ChatModerationPermissionsSeeder`, idempotent (firstOrCreate).

---

## Foreign Key & Index Summary

| FK | On Delete | Index |
|---|---|---|
| `chat_message_log.chat_thread_id → chat_threads.id` | RESTRICT | `cml_thread_firestore_unique`, `cml_thread_created_idx` |
| `chat_message_log.sender_id → users.id` | RESTRICT | — |
| `chat_moderation_flags.chat_message_log_id → chat_message_log.id` | RESTRICT | `cmf_log_type_unique` |
| `chat_moderation_flags.reviewed_by → users.id` | SET NULL | `cmf_reviewed_idx` |
