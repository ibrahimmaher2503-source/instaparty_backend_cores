<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Contracts;

use App\Modules\Shared\Domain\Enums\TriggerKind;

interface StateTransitionLogger
{
    /**
     * Record a state transition synchronously within the current DB transaction.
     *
     * @param  class-string  $transitionableType
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
