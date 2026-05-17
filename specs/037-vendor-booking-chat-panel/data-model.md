# Data Model: Vendor Booking Chat Panel (RestrictedChatPanel)

**Feature**: `specs/037-vendor-booking-chat-panel`
**Date**: 2026-05-16

---

## Schema changes

**No new database tables** are introduced by this spec. All tables are either pre-existing or created by spec 036.

| Table | Source | This spec's role |
|---|---|---|
| `chat_threads` | Existing (migrations `2026_05_16_100003` + `2026_05_16_100004`) | Read — panel state, freeze banner |
| `chat_message_log` | Spec 036 migration | Read (history display) + Insert (vendor send + block) |
| `chat_moderation_flags` | Spec 036 migration | Insert only (on blocked messages) |
| `booking_vendors` | Existing | Read — `sub_status`, `vendor_profile_id` |
| `bookings` | Existing | Read — `lifecycle_status` |
| `audit_logs` | Existing (append-only) | Insert — `chat.message_sent`, `chat.message_blocked` |

---

## Eloquent models

### `ChatThread` (existing — `app/Modules/Communication/Domain/Models/ChatThread.php`)

No column additions. The following relationships are **added** to the existing model:

```php
// Add to ChatThread:
public function messages(): HasMany
{
    return $this->hasMany(ChatMessageLog::class)->orderBy('created_at');
}

public function flags(): HasMany
{
    return $this->hasMany(ChatModerationFlag::class, 'chat_thread_id', 'id')
        ->via('chatMessageLog');  // indirect — flags are on message_log rows
}
```

### `ChatMessageLog` (new model — from spec 036)

`app/Modules/Communication/Domain/Models/ChatMessageLog.php`

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT PK | auto-increment |
| `public_id` | CHAR(26) UNIQUE | ULID |
| `chat_thread_id` | BIGINT FK → `chat_threads.id` | restrictOnDelete |
| `firestore_message_id` | VARCHAR(120) UNIQUE | set by gateway on send |
| `sender_id` | BIGINT FK → `users.id` | restrictOnDelete |
| `message_kind` | ENUM('text','image','voice') | 'text' for vendor sends |
| `detected_locale` | CHAR(5) | e.g., `'en'`, `'ar'` |
| `flagged` | BOOLEAN | default false |
| `flag_reason` | VARCHAR(80) NULLABLE | 'phone_pattern', 'email_pattern', 'external_link', 'manual', 'post_lock' |
| `redacted` | BOOLEAN | default false (admin-set via spec 036) |
| `created_at` | TIMESTAMP | useCurrent() — NO updated_at (append-only) |

**Mutable columns**: `flagged`, `flag_reason`, `redacted` only. All other columns are insert-only.

**Casts**:
```php
protected $casts = [
    'flagged'    => 'boolean',
    'redacted'   => 'boolean',
    'created_at' => 'datetime',
];
```

**Relationships**:
```php
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
```

### `ChatModerationFlag` (new model — from spec 036)

`app/Modules/Communication/Domain/Models/ChatModerationFlag.php`

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT PK | auto-increment |
| `public_id` | CHAR(26) UNIQUE | ULID |
| `chat_message_log_id` | BIGINT FK → `chat_message_log.id` | restrictOnDelete |
| `flag_type` | ENUM('phone','email','external_link','profanity','other') | |
| `matched_pattern` | VARCHAR(255) NULLABLE | the captured match |
| `action_taken` | ENUM('block','warn','redact','none') | 'block' for vendor-send blocks |
| `reviewed_by` | BIGINT NULLABLE FK → `users.id` | nullOnDelete — admin reviewer |
| `reviewed_at` | TIMESTAMP NULLABLE | |
| `created_at` | TIMESTAMP | useCurrent() — NO updated_at |

**Casts**:
```php
protected $casts = [
    'reviewed_at' => 'datetime',
    'created_at'  => 'datetime',
];
```

---

## Enums (new)

### `ChatPanelState`

`app/Modules/Communication/Domain/Enums/ChatPanelState.php`

```php
enum ChatPanelState: string
{
    case Placeholder  = 'placeholder';  // thread not yet created
    case Open         = 'open';         // send enabled
    case Frozen       = 'frozen';       // admin freeze — compose hidden, banner shown
    case Closed       = 'closed';       // post-review-window — compose hidden
    case SystemLocked = 'system_locked'; // status=locked but no frozen_at
}
```

### `ChatFlagType` (shared with spec 036, author here)

`app/Modules/Communication/Domain/Enums/ChatFlagType.php`

```php
enum ChatFlagType: string
{
    case Phone       = 'phone';
    case Email       = 'email';
    case ExternalLink = 'external_link';
    case Profanity   = 'profanity';
    case Other       = 'other';
}
```

### `ChatFlagAction` (shared with spec 036, author here)

`app/Modules/Communication/Domain/Enums/ChatFlagAction.php`

```php
enum ChatFlagAction: string
{
    case Block  = 'block';
    case Warn   = 'warn';
    case Redact = 'redact';
    case None   = 'none';
}
```

---

## Value objects

### `OffPlatformMatch`

`app/Modules/Communication/Application/Services/OffPlatformMatch.php`

```php
final readonly class OffPlatformMatch
{
    public function __construct(
        public readonly ChatFlagType $flagType,
        public readonly string $matchedPattern,
    ) {}
}
```

---

## DTOs

### `SendVendorChatMessageDTO`

`app/Modules/Communication/Application/DTOs/SendVendorChatMessageDTO.php`

```php
final readonly class SendVendorChatMessageDTO
{
    public function __construct(
        public readonly int $chatThreadId,
        public readonly int $vendorProfileId,
        public readonly int $senderUserId,
        public readonly string $body,   // max 1000 chars, already validated
    ) {}
}
```

---

## Services (new)

### `OffPlatformPatternDetector`

`app/Modules/Communication/Application/Services/OffPlatformPatternDetector.php`

```
Methods:
  detect(string $body): ?OffPlatformMatch
    - normalizes Eastern Arabic digits first
    - runs Egyptian mobile regex
    - runs E.164 international regex
    - runs email regex
    - runs external-link regex
    - returns first match or null

  normalize(string $body): string
    - maps ٠١٢٣٤٥٦٧٨٩ → 0123456789

Curated regex constants (in class body, tested in unit test):
  PHONE_EGYPTIAN  = '/(?:\+?20|0)?\s*1\s*[0-2,5]\s*\d(?:[\s.\-]*\d){7}/u'
  PHONE_E164      = '/\+\d{10,15}/'
  EMAIL           = '/[\w.+-]+@[\w-]+\.[\w.-]+/i'
  EXTERNAL_LINK   = '/https?:\/\/(?!instaparty\.eg)/i'
```

### `ChatPanelStateResolver`

`app/Modules/Communication/Application/Services/ChatPanelStateResolver.php`

```
Method:
  resolve(?ChatThread $thread, BookingVendor $bookingVendor, Booking $booking): ChatPanelState

State precedence (checked in order):
  1. thread === null                                               → Placeholder
  2. thread->frozen_at !== null                                   → Frozen
  3. thread->status === 'closed'                                  → Closed
  4. booking->lifecycle_status IN ('cancelled', 'completed')     → Closed
  5. bookingVendor->sub_status NOT IN ('pending', 'modified')    → Closed
  6. thread->status === 'locked' && thread->frozen_at === null   → SystemLocked
  7. otherwise                                                    → Open
```

---

## Actions (new)

### `SendVendorChatMessageAction`

`app/Modules/Communication/Application/Actions/SendVendorChatMessageAction.php`

```
Constructor:
  OffPlatformPatternDetector $detector
  FirestoreChatGateway $gateway
  ChatPanelStateResolver $stateResolver

execute(ChatThread $thread, VendorProfile $vendor, SendVendorChatMessageDTO $dto): ChatMessageLog

Algorithm:
  1. Assert $thread->vendor_profile_id === $vendor->id          → abort 403
  2. Resolve BookingVendor + Booking for this thread             → eager-load
  3. Compute panelState via ChatPanelStateResolver               → abort 422 if !== Open
  4. Check Redis dedup key (10s TTL)                             → return cached if hit
  5. $match = $detector->detect($dto->body)
  6. DB::transaction():
     a. Insert ChatMessageLog (flagged = $match !== null, flag_reason = $match?->flagType->value)
     b. If $match !== null:
        Insert ChatModerationFlag (action_taken = 'block', flag_type, matched_pattern)
        Insert AuditLog (action = 'chat.message_blocked', subject = ChatModerationFlag)
        DB::afterCommit(): fire ChatFlagged event
        → throw BlockedMessageException (caught by Livewire component → 422 + toast)
     c. If $match === null:
        Insert AuditLog (action = 'chat.message_sent', subject = ChatMessageLog)
        DB::afterCommit(): call $gateway->sendMessage($thread->firestore_thread_id, $dto->senderUserId, $dto->body)
  7. Store dedup key in Redis → cached ChatMessageLog id
  8. Return ChatMessageLog
```

**Note on gateway call placement**: The Firestore gateway call happens in `DB::afterCommit()` so a gateway failure does not leave an orphaned `chat_message_log` row — the transaction rolled back before the event fires. If the gateway fails post-commit (rare), the `chat_message_log` row exists but has no Firestore counterpart — the Firestore listener will eventually heal this via reconciliation (Phase 2 concern, documented in ADR-0014).

---

## Contract extension

### `FirestoreChatGateway` (amended)

`app/Modules/Communication/Domain/Contracts/FirestoreChatGateway.php`

```
Add method:
  sendMessage(string $firestoreThreadId, string $senderUserId, string $body): string
  // Returns: Firestore-generated message document ID
```

### `FirestoreChatGatewayStub` (amended)

`app/Modules/Communication/Infrastructure/Gateways/FirestoreChatGatewayStub.php`

```
Add method:
  public function sendMessage(string $firestoreThreadId, string $senderUserId, string $body): string
  {
      Log::info('FirestoreChatGateway::sendMessage', [...]);
      return 'stub_msg_' . Str::uuid();
  }
```

---

## Livewire component

### `RestrictedChatPanel`

`app/Modules/Communication/Filament/Vendor/Components/RestrictedChatPanel.php`

```
Extends: Livewire\Component

Props (mount):
  string $bookingVendorPublicId

Computed / mounted state:
  BookingVendor $bookingVendor   (loaded + ownership-verified in mount)
  ChatThread|null $thread        (loaded via booking_id + vendor_profile_id)
  ChatPanelState $panelState     (resolved by ChatPanelStateResolver)
  Collection $messages           (last 50 from chat_message_log)

Livewire properties:
  string $newMessage = ''        (compose field, wire:model)
  bool $loadingMore = false

Actions:
  sendMessage(): void
    - Validate: $newMessage required, max:1000
    - Call SendVendorChatMessageAction
    - On success: reset $newMessage, refresh $messages
    - On BlockedMessageException: show danger notification (bilingual)
    - On other exception: show warning notification "Could not send — try again"

  loadEarlierMessages(): void
    - Append 50 more messages before the oldest currently loaded
    - Set $loadingMore = true / false

View: resources/views/vendor-portal/components/restricted-chat-panel.blade.php
```

---

## Blade view structure

`resources/views/vendor-portal/components/restricted-chat-panel.blade.php`

```html
<!-- Section wrapper (Filament-compatible styling) -->
<div class="fi-section ...">

  <!-- Header: "Restricted Chat" + status pill -->
  <div class="fi-section-header">
    <h3>{{ __('chat.panel_title') }}</h3>
    <x-filament::badge color="{{ $statusColor }}">{{ $statusLabel }}</x-filament::badge>
  </div>

  <!-- Freeze banner — shown only when panelState = 'frozen' -->
  @if($panelState === \App\Modules\Communication\Domain\Enums\ChatPanelState::Frozen)
  <div class="fi-banner fi-color-danger ...">
    <p>{{ __('chat.freeze_banner_body') }}</p>
    <p class="text-sm">{{ __('chat.frozen_at', ['at' => $thread->frozen_at->format('d M Y, H:i')]) }}</p>
  </div>
  @endif

  <!-- Closed / system-locked indicator -->
  @if(in_array($panelState, [
      \...\ChatPanelState::Closed,
      \...\ChatPanelState::SystemLocked,
      \...\ChatPanelState::Placeholder,
  ]))
  <p class="fi-ta-empty ...">{{ __('chat.closed_label_' . $panelState->value) }}</p>
  @endif

  <!-- Message list -->
  <div class="chat-message-list overflow-y-auto max-h-96 space-y-2 p-4">
    @if($this->canLoadMore)
    <button wire:click="loadEarlierMessages" class="...">{{ __('chat.load_earlier') }}</button>
    @endif

    @foreach($messages as $msg)
    <div class="flex {{ $msg->sender_id === auth()->id() ? 'justify-end' : 'justify-start' }}">
      <div class="rounded-lg px-3 py-2 max-w-xs {{ $msg->sender_id === auth()->id() ? 'bg-primary-100' : 'bg-gray-100' }}">
        @if($msg->redacted)
          <p class="text-gray-400 italic">{{ __('chat.message_redacted') }}</p>
        @elseif($msg->flagged && $msg->flag_reason !== 'post_lock')
          <p class="text-red-500 italic">{{ __('chat.message_blocked_placeholder') }}</p>
        @else
          <p>{{ $msg->body_preview }}</p>
        @endif
        <p class="text-xs text-gray-400 mt-1">{{ $msg->created_at->format('d M Y, H:i') }}</p>
      </div>
    </div>
    @endforeach
  </div>

  <!-- Compose form — shown only when panelState = 'open' -->
  @if($panelState === \...\ChatPanelState::Open)
  <form wire:submit="sendMessage" class="p-4 border-t">
    <div class="flex gap-2">
      <textarea
        wire:model="newMessage"
        maxlength="1000"
        placeholder="{{ __('chat.compose_placeholder') }}"
        class="fi-input w-full"
        rows="2"
      ></textarea>
      <button type="submit" wire:loading.attr="disabled" class="fi-btn fi-btn-primary">
        <span wire:loading.remove>{{ __('chat.send') }}</span>
        <span wire:loading>{{ __('chat.sending') }}</span>
      </button>
    </div>
    <p class="text-xs text-gray-400 mt-1">{{ __('chat.char_count', ['count' => strlen($newMessage), 'max' => 1000]) }}</p>
  </form>
  @endif

</div>
```

---

## Host page changes

### `VendorBookingDetailPage` Blade (`vendor-booking-detail.blade.php`)

```blade
<x-filament-panels::page>
    {{ $this->infolist }}
    @livewire(\App\Modules\Communication\Filament\Vendor\Components\RestrictedChatPanel::class,
        ['bookingVendorPublicId' => $bookingVendor])
</x-filament-panels::page>
```

### `VendorBookingDecisionPage` Blade (`vendor-booking-decision.blade.php`)

Same change — add the `@livewire` call after `{{ $this->infolist }}` (or after the decision actions section, whichever is rendered last).

---

## Translation keys (EN)

`app/Modules/Communication/Resources/lang/en/chat.php` — **new keys to add**:

```php
'panel_title'                    => 'Restricted Chat',
'status_open'                    => 'Open',
'status_frozen'                  => 'Frozen by Admin',
'status_closed'                  => 'Closed',
'status_system_locked'           => 'Temporarily Unavailable',
'status_placeholder'             => 'Not Yet Available',
'freeze_banner_body'             => 'This chat has been frozen by the platform administrator. You may not send new messages until the freeze is lifted.',
'frozen_at'                      => 'Frozen at :at',
'closed_label_closed'            => 'Chat closed — booking no longer in review.',
'closed_label_system_locked'     => 'Chat temporarily unavailable.',
'closed_label_placeholder'       => 'Chat not yet available — please refresh shortly.',
'load_earlier'                   => 'Load earlier messages',
'message_redacted'               => '[Message removed by moderation]',
'message_blocked_placeholder'    => '[Message blocked — compliance]',
'compose_placeholder'            => 'Type a message…',
'send'                           => 'Send',
'sending'                        => 'Sending…',
'char_count'                     => ':count / :max characters',
'blocked_toast_title'            => 'Message Blocked',
'blocked_toast_body'             => 'Your message was blocked: external contact details are not permitted on this platform.',
'send_error_title'               => 'Could not send',
'send_error_body'                => 'There was a problem sending your message. Please try again.',
'message_sent_success'           => 'Message sent.',
```

`app/Modules/Communication/Resources/lang/ar/chat.php` — **same keys in Arabic**:

```php
'panel_title'                    => 'المحادثة المقيدة',
'status_open'                    => 'مفتوحة',
'status_frozen'                  => 'مجمدة من قِبَل المسؤول',
'status_closed'                  => 'مغلقة',
'status_system_locked'           => 'غير متاحة مؤقتاً',
'status_placeholder'             => 'غير متاحة بعد',
'freeze_banner_body'             => 'تم تجميد هذه المحادثة من قِبَل مسؤول المنصة. لا يمكنك إرسال رسائل جديدة حتى يتم رفع التجميد.',
'frozen_at'                      => 'تم التجميد في: :at',
'closed_label_closed'            => 'المحادثة مغلقة — الحجز لم يعد قيد المراجعة.',
'closed_label_system_locked'     => 'المحادثة غير متاحة مؤقتاً.',
'closed_label_placeholder'       => 'المحادثة غير متاحة بعد — يرجى تحديث الصفحة.',
'load_earlier'                   => 'تحميل رسائل أقدم',
'message_redacted'               => '[تمت إزالة هذه الرسالة بواسطة الإشراف]',
'message_blocked_placeholder'    => '[رسالة محجوبة — امتثال]',
'compose_placeholder'            => 'اكتب رسالة…',
'send'                           => 'إرسال',
'sending'                        => 'جارٍ الإرسال…',
'char_count'                     => ':count / :max حرف',
'blocked_toast_title'            => 'تم حظر الرسالة',
'blocked_toast_body'             => 'تم حظر رسالتك: لا يُسمح بمعلومات الاتصال الخارجية على هذه المنصة.',
'send_error_title'               => 'تعذّر الإرسال',
'send_error_body'                => 'حدثت مشكلة أثناء إرسال رسالتك. يرجى المحاولة مرة أخرى.',
'message_sent_success'           => 'تم إرسال الرسالة.',
```
