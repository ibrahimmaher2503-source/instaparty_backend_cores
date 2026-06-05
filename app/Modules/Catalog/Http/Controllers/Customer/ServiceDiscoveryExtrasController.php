<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Customer;

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\States\ServiceStatus\PublishedState;
use App\Modules\Discovery\Http\Resources\ServiceSearchResultResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Audit 6.2 (suggestions), 7.4 (similar), 7.5 (view tracking).
 *
 * Suggestions are DB-backed (JSON-name LIKE) in Phase 1 — a Meilisearch
 * query-suggestions index is a Phase 2 performance optimization.
 *
 * @group Customer - Catalog
 */
class ServiceDiscoveryExtrasController
{
    public function suggestions(Request $request): JsonResponse
    {
        $validated = $request->validate(['q' => ['required', 'string', 'min:2', 'max:80']]);

        $locale = app()->getLocale();
        $needle = '%'.str_replace(['%', '_'], ['\%', '\_'], $validated['q']).'%';

        $rows = Service::query()
            ->whereState('status', PublishedState::class)
            ->where(fn ($q) => $q
                ->where('name->en', 'like', $needle)
                ->orWhere('name->ar', 'like', $needle))
            ->orderByDesc('rating_avg')
            ->limit(8)
            ->get()
            ->map(fn (Service $service): array => [
                'public_id' => $service->public_id,
                'name' => $service->getTranslation('name', $locale, useFallbackLocale: true),
                'product_type' => $service->product_type->value,
            ])
            ->values();

        return ApiResponse::success($rows);
    }

    public function similar(string $servicePublicId): JsonResponse
    {
        $service = $this->publishedService($servicePublicId);

        $similar = Service::query()
            ->whereState('status', PublishedState::class)
            ->where('category_id', $service->category_id)
            ->where('product_type', $service->product_type)
            ->whereKeyNot($service->id)
            ->with(['category', 'vendor'])
            ->orderByDesc('rating_avg')
            ->limit(6)
            ->get();

        return ApiResponse::success(
            ServiceSearchResultResource::collection($similar)
        );
    }

    public function trackView(string $servicePublicId): JsonResponse
    {
        $service = $this->publishedService($servicePublicId);

        DB::table('analytics_events')->insert([
            'event_type' => 'service_view',
            'payload' => json_encode([
                'service_id' => $service->id,
                'product_type' => $service->product_type->value,
                'user_id' => auth('sanctum')->id(),
            ]),
            'created_at' => now(),
        ]);

        return ApiResponse::success(null, [], 202);
    }

    private function publishedService(string $publicId): Service
    {
        return Service::query()
            ->whereState('status', PublishedState::class)
            ->where('public_id', $publicId)
            ->firstOrFail();
    }
}
