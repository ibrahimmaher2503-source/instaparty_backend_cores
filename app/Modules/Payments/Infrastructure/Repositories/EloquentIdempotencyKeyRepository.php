<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Repositories;

use App\Modules\Payments\Domain\Models\IdempotencyKey;

class EloquentIdempotencyKeyRepository
{
    public function lookup(string $key, ?int $userId, string $route): ?IdempotencyKey
    {
        return IdempotencyKey::query()
            ->active()
            ->where('key', $key)
            ->where('route', $route)
            ->where('user_id', $userId)
            ->first();
    }

    public function put(string $key, ?int $userId, string $route, string $hash, int $status, array $body): void
    {
        IdempotencyKey::query()->updateOrCreate(
            ['key' => $key],
            [
                'user_id' => $userId,
                'route' => $route,
                'request_hash' => $hash,
                'response_status' => $status,
                'response_body' => $body,
                'expires_at' => now()->addHours(24),
                'created_at' => now(),
            ]
        );
    }
}
