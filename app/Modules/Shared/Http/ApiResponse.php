<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(mixed $data, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => (object) $meta,
            'errors' => null,
        ], $status);
    }

    public static function error(array|string $errors, int $status = 422, array $meta = []): JsonResponse
    {
        return response()->json([
            'data' => null,
            'meta' => (object) $meta,
            'errors' => is_string($errors) ? ['message' => $errors] : $errors,
        ], $status);
    }
}
