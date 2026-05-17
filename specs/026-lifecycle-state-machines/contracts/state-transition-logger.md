# Contract: StateTransitionLogger

**Module**: Shared  
**Path**: `app/Modules/Shared/Domain/Contracts/StateTransitionLogger.php`  
**Purpose**: Internal contract for writing rows to `state_transitions`. No external API — purely for
cross-module use within the modular monolith.

---

## Interface

```php
<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Contracts;

interface StateTransitionLogger
{
    /**
     * Record a state transition synchronously within the current DB transaction.
     *
     * @param  class-string  $transitionableType  Eloquent model FQCN
     * @param  int           $transitionableId
     * @param  string|null   $fromState           DB value of the state before transition (null if initial)
     * @param  string        $toState             DB value of the state after transition
     * @param  int|null      $triggeredBy         users.id of the actor (null for system transitions)
     * @param  TriggerKind   $triggerKind
     * @param  string|null   $reason              Human-readable reason; REQUIRED when triggerKind = admin_override
     * @param  string|null   $traceId             UUID/ULID from originating request or job
     * @param  array|null    $context             Structured metadata (gateway refs, batch IDs, etc.)
     */
    public function record(
        string $transitionableType,
        int $transitionableId,
        ?string $fromState,
        string $toState,
        ?int $triggeredBy,
        TriggerKind $triggerKind,
        ?string $reason,
        ?string $traceId,
        ?array $context,
    ): void;
}
```

---

## `TriggerKind` Enum

```php
<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Enums;

enum TriggerKind: string
{
    case System        = 'system';
    case Customer      = 'customer';
    case Vendor        = 'vendor';
    case Admin         = 'admin';
    case AdminOverride = 'admin_override';

    public function requiresReason(): bool
    {
        return $this === self::AdminOverride;
    }
}
```

---

## Implementation

`StateTransitionObserver` implements this contract and is the **only** implementation in
Phase 7.1. It is bound in `SharedServiceProvider`:

```php
$this->app->bind(
    StateTransitionLogger::class,
    StateTransitionObserver::class,
);
```

---

## Usage (within Transition classes)

Transition classes do NOT call `StateTransitionLogger` directly. They set values on Laravel
`Context`, and the observer (registered on the `StateChanged` event) calls the logger.

The logger is available for explicit use in edge cases:
- Queue jobs that trigger state changes without going through `transitionTo()` (rare)
- Admin-initiated batch operations where a single `trace_id` should cover many transitions

In those cases, inject `StateTransitionLogger` and call `->record(...)`.

---

## Invariants

1. `record()` is called inside the same `DB::transaction` as the model state column update.
   If `record()` throws, the transaction rolls back and the state column change is undone.
2. `reason` MUST be non-empty when `triggerKind = AdminOverride`. The implementation validates
   this and throws `\InvalidArgumentException` if violated.
3. The written row is **never** updated or deleted. Treat as append-only.
