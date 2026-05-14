<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Services;

use App\Modules\Shared\Http\ApiResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IdempotencyService
{
    /**
     * Wrap an operation with idempotency protection.
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
            // Same key, different payload — caller is misusing the key
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
            ]]);
        }

        return $response;
    }
}
