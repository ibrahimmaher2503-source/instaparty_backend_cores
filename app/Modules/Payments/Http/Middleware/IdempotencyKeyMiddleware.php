<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Middleware;

use App\Modules\Payments\Domain\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\JsonResponse;
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

        $rawBody = (string) $request->getContent();
        $hash = hash('sha256', $rawBody);

        $existing = IdempotencyKey::query()
            ->where('key', $key)
            ->where('expires_at', '>', now())
            ->first();

        if ($existing !== null) {
            if ($existing->request_hash !== $hash) {
                return response()->json(['data' => null, 'meta' => (object) [], 'errors' => ['Idempotency key conflict']], 409);
            }

            $body = $existing->response_body ?? ['data' => null, 'meta' => (object) [], 'errors' => []];
            return response()->json($body, (int) ($existing->response_status ?? 200));
        }

        $response = $next($request);

        if ($response->getStatusCode() < 400) {
            $body = $response instanceof JsonResponse ? $response->getData(true) : null;
            IdempotencyKey::query()->updateOrCreate(
                ['key' => $key],
                [
                    'user_id' => optional($request->user())->id,
                    'route' => $request->route()?->uri() ?? $request->path(),
                    'request_hash' => $hash,
                    'response_status' => $response->getStatusCode(),
                    'response_body' => $body,
                    'expires_at' => now()->addHours(24),
                ],
            );
        }

        return $response;
    }
}
