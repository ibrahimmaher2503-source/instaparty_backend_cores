<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Concerns;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/**
 * Provides helpers for reading and propagating correlation / causation IDs
 * from Laravel's Context store into ledger entries and outbound events.
 *
 * Include in any Action that posts ledger entries or fires domain events.
 */
trait ThreadsCausalChain
{
    protected function correlationId(): string
    {
        return Context::get('correlation_id') ?? (string) Str::ulid();
    }

    protected function causationId(): string
    {
        return Context::get('causation_id') ?? (string) Str::ulid();
    }

    protected function traceId(): ?string
    {
        return Context::get('trace_id');
    }

    /**
     * Push a new causation ID into context so child operations reference this action
     * as their direct cause, while preserving the top-level correlation ID.
     */
    protected function withCausationId(string $causationId, callable $callback): mixed
    {
        $previous = Context::get('causation_id');

        Context::add('causation_id', $causationId);

        try {
            return $callback();
        } finally {
            if ($previous !== null) {
                Context::add('causation_id', $previous);
            }
        }
    }

    /**
     * Permanently advance the causation pointer to this event ID.
     * Use when the current step IS the new causal root for downstream work.
     * Preserves the top-level correlation_id.
     */
    protected function forNewCause(string $eventId): string
    {
        Context::add('causation_id', $eventId);

        return $eventId;
    }
}
