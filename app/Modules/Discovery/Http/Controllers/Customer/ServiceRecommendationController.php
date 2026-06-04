<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Http\Controllers\Customer;

use App\Modules\Discovery\Http\Resources\ServiceSearchResultResource;
use App\Modules\Discovery\Infrastructure\Repositories\ServiceRecommendationRepository;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * POST /customer/discovery/recommendations — wizard-context scored services
 * (occasion 40 / city 30 / age 20 / price 10). Public: guests get
 * recommendations too; the client passes wizard state directly (ruling:
 * 9.1 wizard-session dropped — state lives in Hive/localStorage).
 */
class ServiceRecommendationController
{
    public function __invoke(Request $request, ServiceRecommendationRepository $repository): JsonResponse
    {
        $validated = $request->validate([
            'occasion_public_id' => ['nullable', 'string', 'max:26'],
            'city_public_id' => ['nullable', 'string', 'max:26'],
            'child_age' => ['nullable', 'integer', 'min:0', 'max:18'],
            'budget_minor' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:24'],
        ]);

        $services = $repository->recommend(
            $this->idFor('occasions', $validated['occasion_public_id'] ?? null),
            $this->idFor('cities', $validated['city_public_id'] ?? null),
            $validated['budget_minor'] ?? null,
            (int) ($validated['limit'] ?? 10),
        );

        return ApiResponse::success(
            ServiceSearchResultResource::collection($services),
            ['age_scored' => false], // no age data on services in Phase 1
        );
    }

    private function idFor(string $table, ?string $publicId): ?int
    {
        if ($publicId === null) {
            return null;
        }

        $id = DB::table($table)->where('public_id', $publicId)->value('id');

        return $id !== null ? (int) $id : null;
    }
}
