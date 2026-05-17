# Phase 1 — Data Model: Communication Provider Health

**Date**: 2026-05-17
**Spec**: [./spec.md](./spec.md)
**Plan**: [./plan.md](./plan.md)

---

## 1. Schema changes — `notification_dispatches` (existing table)

⚠️ **Not in `docs/specs/11_DB_Schema.md` yet.** Backfill the table definition in that document when this phase ships.

Existing columns (verified in `app/Modules/Communication/Database/Migrations/2026_05_03_100002_create_notification_dispatches_table.php`):

| Column | Type | Null | Status |
|---|---|---|---|
| `id` | BIGINT UNSIGNED PK | NO | kept |
| `public_id` | CHAR(26) UNIQUE | NO | kept |
| `notification_template_id` | BIGINT UNSIGNED NULL FK | YES | kept |
| `user_id` | BIGINT UNSIGNED FK | NO | kept |
| `channel` | VARCHAR(20) | NO | kept |
| `locale` | VARCHAR(10) | NO | kept |
| `status` | VARCHAR(20) | NO | kept (mutable) |
| `context` | JSON | YES | kept |
| `provider` | VARCHAR(50) | YES | **legacy** — kept; dual-written from `provider_name` |
| `provider_ref` | VARCHAR(255) | YES | **legacy** — kept; dual-written from `provider_message_id` |
| `reference_type` | VARCHAR(100) | YES | kept |
| `reference_id` | BIGINT UNSIGNED | YES | kept |
| `error_message` | TEXT | YES | **legacy** — kept; dual-written from `provider_error_message` |
| `sent_at` | TIMESTAMP | YES | kept (mutable) |
| `delivered_at` | TIMESTAMP | YES | kept (mutable) |
| `created_at` | TIMESTAMP | NO | kept |

**Columns added by `2026_05_17_100001_extend_notification_dispatches_for_provider_health.php`**:

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `provider_name` | VARCHAR(60) | YES | NULL | Replaces `provider`. Adapter `name()` writes here. |
| `provider_message_id` | VARCHAR(255) | YES | NULL | Replaces `provider_ref`. Provider's own message/delivery ID. |
| `provider_status` | VARCHAR(60) | YES | NULL | Raw provider status string (e.g., FCM `messageId`, Vonage `"0"`, Mailchimp `"sent"`). |
| `provider_error_code` | VARCHAR(60) | YES | NULL | Provider-specific error code (FCM `"UNREGISTERED"`, Vonage `"1"`, Mailchimp `"reject"`). |
| `provider_error_message` | TEXT | YES | NULL | Replaces `error_message`. Human-readable provider error. |
| `attempt_count` | TINYINT UNSIGNED | NO | 0 | Increments by 1 on every `send()` attempt. Hard cap at 5. |
| `last_attempt_at` | TIMESTAMP | YES | NULL | UTC. Updated by `DispatchNotificationJob` immediately before `send()`. |
| `next_retry_at` | TIMESTAMP | YES | NULL | UTC. Set on failure to schedule auto-retry. NULL when permanently failed or successful. |
| `is_test` | BOOLEAN | NO | FALSE | TRUE for dispatches created by `SendTestXxxAction`. |
| `updated_at` | TIMESTAMP | YES | NULL | New — supports mutable tracking fields. Existing rows have NULL `updated_at`. |

**New indexes**:

| Index | Columns | Why |
|---|---|---|
| `idx_dispatches_retry_scan` | `(status, next_retry_at)` | `RetryFailedDispatchesCommand` scan: `WHERE status='failed' AND next_retry_at <= NOW()` |
| `idx_dispatches_provider_name` | `(provider_name, status, created_at)` | Health page channel grouping + status breakdown |
| `idx_dispatches_is_test` | `(is_test, created_at)` | Filament filter on test dispatches |

**Migration constraints**:
- Use `Schema::table('notification_dispatches', ...)`, not `Schema::create`.
- All `add` operations are non-blocking on InnoDB (instant DDL for nullable columns + indexes added via `ALGORITHM=INPLACE` where possible — Laravel handles this).
- `updated_at` is nullable to avoid back-filling existing rows.
- No `dropColumn` calls in this migration.

**Append-only invariant check**: `notification_dispatches` is NOT listed in the Constitution §V append-only set. Mutable status/tracking columns are permitted. The migration is compliant.

---

## 2. Model changes — `App\Modules\Communication\Domain\Models\NotificationDispatch`

**Current state** (`app/Modules/Communication/Domain/Models/NotificationDispatch.php`):
- `public $timestamps = false;`
- `const CREATED_AT = 'created_at';`
- `const UPDATED_AT = null;`
- `$fillable` does not include the new columns.

**Changes required**:

1. Change `public $timestamps = false;` → `public $timestamps = true;`
2. Remove `const CREATED_AT` and `const UPDATED_AT` overrides (use Laravel defaults).
3. Extend `$fillable` to include: `provider_name`, `provider_message_id`, `provider_status`, `provider_error_code`, `provider_error_message`, `attempt_count`, `last_attempt_at`, `next_retry_at`, `is_test`.
4. Extend `casts()` to include: `'last_attempt_at' => 'datetime'`, `'next_retry_at' => 'datetime'`, `'updated_at' => 'datetime'`, `'is_test' => 'boolean'`, `'attempt_count' => 'integer'`.
5. Add scope helpers (composable, used by health page and scheduler):
   - `scopeWithStatus(Builder $q, DispatchStatus $status)`
   - `scopeForChannel(Builder $q, NotificationChannel $channel)`
   - `scopeInLast24h(Builder $q)`
   - `scopeRetryable(Builder $q)` — `status=failed AND attempt_count < 5 AND next_retry_at <= now()`
6. Add accessor `getIsRetryableAttribute(): bool` — true when `status === DispatchStatus::Failed && attempt_count < 5`.

**No business logic added to the model** (per Constitution §I.II — models hold relationships, casts, scopes only).

---

## 3. New Enum — none

The existing `DispatchStatus` enum (`Queued | Sent | Delivered | Failed | Bounced`) is sufficient. No new statuses introduced.

---

## 4. Value Object — `ProviderHealthResult`

**Location**: `app/Modules/Communication/Domain/ValueObjects/ProviderHealthResult.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\ValueObjects;

use Carbon\CarbonImmutable;

final readonly class ProviderHealthResult
{
    public function __construct(
        public string $providerName,
        public bool $isReachable,
        public ?int $latencyMs,
        public ?string $note,
        public CarbonImmutable $checkedAt,
    ) {}

    public static function reachable(string $providerName, int $latencyMs, ?string $note = null): self
    {
        return new self($providerName, true, $latencyMs, $note, CarbonImmutable::now());
    }

    public static function unreachable(string $providerName, ?string $note = null): self
    {
        return new self($providerName, false, null, $note, CarbonImmutable::now());
    }

    public function toArray(): array
    {
        return [
            'provider_name' => $this->providerName,
            'is_reachable' => $this->isReachable,
            'latency_ms' => $this->latencyMs,
            'note' => $this->note,
            'checked_at' => $this->checkedAt->toIso8601String(),
        ];
    }
}
```

**Validation rules**:
- `latencyMs` MUST be NULL if `isReachable` is FALSE.
- `note` MUST NOT contain provider secrets — the adapter is responsible for sanitising.

---

## 5. DTOs — one per channel

**Common shape** (`app/Modules/Communication/Application/DTOs/TestPushDTO.php` and three siblings):

```php
final readonly class TestPushDTO
{
    public function __construct(
        public string $deviceToken,
        public int $adminUserId,
        public ?string $bodyOverride = null,
    ) {}
}

final readonly class TestSmsDTO
{
    public function __construct(
        public string $phoneE164,
        public int $adminUserId,
        public ?string $bodyOverride = null,
    ) {}
}

final readonly class TestWhatsAppDTO
{
    public function __construct(
        public string $phoneE164,
        public int $adminUserId,
        public ?string $bodyOverride = null,
    ) {}
}

final readonly class TestEmailDTO
{
    public function __construct(
        public string $emailAddress,
        public int $adminUserId,
        public ?string $subjectOverride = null,
        public ?string $bodyOverride = null,
    ) {}
}
```

**Validation lives in the matching FormRequest** (`SendTestPushRequest`, etc.):

| DTO | Field | Rules |
|---|---|---|
| `TestPushDTO` | `deviceToken` | `required\|string\|min:32\|max:255` |
| `TestSmsDTO` | `phoneE164` | `required\|regex:/^\+[1-9]\d{6,14}$/` |
| `TestWhatsAppDTO` | `phoneE164` | `required\|regex:/^\+[1-9]\d{6,14}$/` |
| `TestEmailDTO` | `emailAddress` | `required\|email:rfc,dns` |
| All | `bodyOverride` | `nullable\|string\|max:500` |
| `TestEmailDTO` | `subjectOverride` | `nullable\|string\|max:160` |

---

## 6. Exceptions

**`App\Modules\Communication\Application\Exceptions\DispatchNotRetryableException`**:
Thrown by `RetryFailedDispatchAction::execute()` when:
- `$dispatch->status !== DispatchStatus::Failed` (cannot retry non-failed)
- `$dispatch->attempt_count >= 5` (cap reached)

Constructor: `public function __construct(public readonly NotificationDispatch $dispatch, public readonly string $reason)`.

**`App\Modules\Communication\Application\Exceptions\NullAdapterInProductionException`**:
Thrown by `NullProviderAdapter::send()` when `app()->environment('production')`.

Constructor: `public function __construct(string $message = 'NullProviderAdapter must never be used in production.')`.

---

## 7. Domain Event — `NotificationDispatchRetried`

**Location**: `app/Modules/Communication/Domain/Events/NotificationDispatchRetried.php`

```php
final class NotificationDispatchRetried
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly NotificationDispatch $dispatch,
        public readonly int $previousAttemptCount,
        public readonly int $adminUserId,
    ) {}
}
```

Fired by `RetryFailedDispatchAction::execute()` via `DB::afterCommit(fn () => event(new NotificationDispatchRetried(...)))`.

**No listeners** are registered in this phase; the event is purely for downstream observability (e.g., Reporting module subscribes to it later).

---

## 8. Job — `DispatchNotificationJob`

**Location**: `app/Modules/Communication/Application/Jobs/DispatchNotificationJob.php`

```php
final class DispatchNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;          // Retry is handled by RetryFailedDispatchesCommand, not by the queue layer
    public int $timeout = 30;
    public int $backoff = 0;

    public function __construct(public readonly int $dispatchId) {}

    public function handle(Container $container): void
    {
        $dispatch = NotificationDispatch::lockForUpdate()->findOrFail($this->dispatchId);

        $dispatch->update([
            'attempt_count' => $dispatch->attempt_count + 1,
            'last_attempt_at' => now(),
        ]);

        $adapter = $container->make($dispatch->channel->value . '_adapter');
        // Adapter is responsible for writing status, provider_*, sent_at on success
        // or status=failed, provider_error_*, and computing next_retry_at on failure.
        $adapter->send($dispatch);

        // If the adapter left status=failed, the job is considered complete (the scheduler handles retry).
        // If the adapter threw, Laravel will mark the job as failed; we leave next_retry_at unset
        // because the failure was infrastructure-level (worker died), not provider-level.
    }
}
```

**Key behaviours**:
- `$tries = 1` because retry logic lives in `RetryFailedDispatchesCommand`, not the queue layer (avoids double retry).
- `lockForUpdate()` prevents two parallel jobs from incrementing `attempt_count` on the same dispatch.
- Adapter is the single owner of post-send state (status, provider_*, sent_at, next_retry_at on failure).

---

## 9. Authorization — Spatie permissions

New permissions registered via `php artisan shield:generate --all`:

| Permission name | Granted to (default) | Used by |
|---|---|---|
| `view_communication_provider_health` | `admin` | `CommunicationProviderHealthPage::canAccess()` |
| `send_test_notification` | `admin` | All four `SendTestXxxAction` Filament actions |
| `retry_notification_dispatch` | `admin` | `NotificationDispatchResource` retry row action + `RetryFailedDispatchAction` (when called from UI) |

Seeded in `Database/Seeders/CommunicationPermissionsSeeder.php` (existing file — append three new lines).

---

## 10. State transitions — `NotificationDispatch.status`

```
queued ────send()──> sent ────[provider webhook]──> delivered
   │                  │
   │                  └────[provider webhook]──> bounced  (terminal)
   │
   └────send() throws──> failed ──┬──[RetryFailedDispatchAction or auto-retry]──> queued
                                  │
                                  └──[attempt_count >= 5]──> failed (terminal; next_retry_at = NULL)
```

The state machine is implicit in the existing `DispatchStatus` enum — no `spatie/laravel-model-states` integration in this phase (overkill for 5 simple states). A future phase may formalise it if mutation invariants need stricter enforcement.
