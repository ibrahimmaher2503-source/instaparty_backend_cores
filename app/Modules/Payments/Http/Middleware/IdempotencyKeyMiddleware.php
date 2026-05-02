<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Middleware;

use App\Modules\Payments\Domain\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) $request->header('Idempotency-Key', '');
        if ($key === '') {
            return response()->json(['data' => null, 'meta' => (object) [], 'errors' => ['Idempotency-Key header is required']], 422);
        }

        $hash = hash('sha256', json_encode($request->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
        $existing = IdempotencyKey::query()
            ->where('key', $key)
            ->where('expires_at', '>', now())
            ->first();

        if ($existing !== null) {
            if ($existing->request_hash !== $hash) {
                return response()->json(['data' => null, 'meta' => (object) [], 'errors' => ['Idempotency key conflict']], 409);
            }

            return response()->json($existing->response_body ?? ['data' => null, 'meta' => (object) [], 'errors' => []], (int) ($existing->response_status ?? 200));
        }

        return $next($request);
    }
}
