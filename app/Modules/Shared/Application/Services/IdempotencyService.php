<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Services;

use App\Modules\Settlement\Domain\Exceptions\DuplicateIdempotencyKeyWithDifferentPayloadException;
use App\Modules\Shared\Http\ApiResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IdempotencyService
{
    /**
     * Wrap an operation with idempotency protection for HTTP requests.
     *
     * Returns 422 when the Idempotency-Key header is absent.
     * Returns the cached response when the key was seen within the last 24 h.
     * Stores only 2xx responses — error responses are never cached.
     */
    public function wrap(Request $request, string $route, Closure $callback): JsonResponse
    {
        if (! $request->hasHeader('Idempotency-Key')) {
            return ApiResponse::error('The Idempotency-Key header is required.', 422);
        }

        $key         = $request->header('Idempotency-Key');
        $requestHash = hash('sha256', $route . '|' . $request->getContent());

        $cached = DB::table('idempotency_keys')
            ->where('key', $key)
            ->where('user_id', auth()->id())
            ->where('expires_at', '>', now())
            ->first();

        if ($cached) {
            if (! hash_equals($cached->request_hash, $requestHash)) {
                return ApiResponse::error('Idempotency-Key reused with a different request payload.', 409);
            }

            return response()->json(json_decode($cached->response_body, true), $cached->response_status);
        }

        $response = $callback();

        if ($response->getStatusCode() < 400) {
            DB::table('idempotency_keys')->insertOrIgnore([[
                'key'             => $key,
                'user_id'         => auth()->id(),
                'route'           => $route,
                'request_hash'    => $requestHash,
                'response_status' => $response->getStatusCode(),
                'response_body'   => $response->getContent(),
                'expires_at'      => now()->addHours(24),
                'created_at'      => now(),
                'scope'           => 'http',
                'ttl_seconds'     => 86400,
                'payload_hash'    => $requestHash,
            ]]);
        }

        return $response;
    }

    /**
     * Wrap an internal money-mutating operation with idempotency protection.
     *
     * Scope allows distinguishing between HTTP keys (24h), internal webhooks (30d), and
     * internal actions (24h). TTL defaults come from config/idempotency.php.
     *
     * Throws DuplicateIdempotencyKeyWithDifferentPayloadException when the same key is reused
     * with a different payload within the TTL window.
     *
     * @template T
     * @param  Closure(): T  $callback
     * @return T
     */
    public function remember(string $scope, string $key, mixed $payload, Closure $callback): mixed
    {
        $ttlSeconds  = $this->ttlForScope($scope);
        $payloadHash = hash('sha256', is_string($payload) ? $payload : json_encode($payload));

        $existing = DB::table('idempotency_keys')
            ->where('key', $key)
            ->where('scope', $scope)
            ->whereRaw('created_at + INTERVAL ttl_seconds SECOND > NOW()')
            ->first();

        if ($existing !== null) {
            if (! hash_equals((string) $existing->payload_hash, $payloadHash)) {
                throw new DuplicateIdempotencyKeyWithDifferentPayloadException(
                    "Idempotency key '{$key}' was already used with a different payload (scope={$scope})."
                );
            }

            $decoded = json_decode((string) ($existing->response_body ?? 'null'), true);

            return $decoded;
        }

        $result = $callback();

        DB::table('idempotency_keys')->insertOrIgnore([[
            'key'             => $key,
            'user_id'         => null,
            'route'           => $scope . ':' . $key,
            'request_hash'    => $payloadHash,
            'response_status' => 200,
            'response_body'   => json_encode($result),
            'expires_at'      => now()->addSeconds($ttlSeconds),
            'created_at'      => now(),
            'scope'           => $scope,
            'ttl_seconds'     => $ttlSeconds,
            'payload_hash'    => $payloadHash,
        ]]);

        return $result;
    }

    /**
     * Check if a key has already been used within its TTL window (read-only).
     */
    public function hasBeenUsed(string $scope, string $key): bool
    {
        return DB::table('idempotency_keys')
            ->where('key', $key)
            ->where('scope', $scope)
            ->whereRaw('created_at + INTERVAL ttl_seconds SECOND > NOW()')
            ->exists();
    }

    private function ttlForScope(string $scope): int
    {
        return match ($scope) {
            'internal_webhook' => (int) config('idempotency.internal_webhook', 2592000),
            'internal_action'  => (int) config('idempotency.internal_action', 86400),
            default            => (int) config('idempotency.http', 86400),
        };
    }
}
