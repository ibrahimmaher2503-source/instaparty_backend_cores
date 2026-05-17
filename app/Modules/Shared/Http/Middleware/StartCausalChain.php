<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Seeds correlation_id and causation_id into Laravel's Context store for every
 * incoming HTTP request so that all ledger entries and domain events in the same
 * request share a traceable causal chain.
 *
 * correlation_id — top-level business operation identifier (flows across all events)
 * causation_id   — the immediate cause of each ledger entry (changes per action)
 *
 * Clients may supply X-Correlation-Id to propagate an existing chain (e.g., from
 * a queue worker retrying a webhook). Otherwise a fresh ULID is generated.
 */
class StartCausalChain
{
    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = $request->header('X-Correlation-Id') ?: (string) Str::ulid();
        $causationId   = (string) Str::ulid();

        Context::add('correlation_id', $correlationId);
        Context::add('causation_id', $causationId);

        $response = $next($request);

        $response->headers->set('X-Correlation-Id', $correlationId);

        return $response;
    }
}
