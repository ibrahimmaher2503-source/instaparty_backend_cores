<?php

declare(strict_types=1);

namespace App\Modules\Geography\Http\Controllers;

use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Geography\Domain\Models\Region;
use App\Modules\Geography\Http\Resources\CityResource;
use App\Modules\Geography\Http\Resources\GovernorateResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Customer - Geography
 */
class GeographyCustomerController
{
    public function governorates(): JsonResponse
    {
        $rows = Governorate::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(GovernorateResource::collection($rows));
    }

    public function cities(Request $request): JsonResponse
    {
        $request->validate([
            'governorate' => ['nullable', 'string', 'exists:governorates,public_id'],
        ]);

        $query = City::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($request->filled('governorate')) {
            $governorateId = Governorate::query()
                ->where('public_id', $request->string('governorate'))
                ->value('id');

            if ($governorateId !== null) {
                $query->forGovernorate((int) $governorateId);
            }
        }

        return ApiResponse::success(CityResource::collection($query->get()));
    }

    /**
     * Regions ("areas") inside a governorate. Audit 18.6 asked for
     * cities/{id}/areas — reshaped to the LOCKED Geography hierarchy
     * (governorates → regions → cities).
     */
    public function regions(string $governoratePublicId): JsonResponse
    {
        $governorate = Governorate::query()
            ->where('public_id', $governoratePublicId)
            ->firstOrFail();

        $locale = app()->getLocale();

        $regions = Region::query()
            ->where('governorate_id', $governorate->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn ($region): array => [
                'public_id' => $region->public_id,
                'name' => $region->getTranslation('name', $locale, useFallbackLocale: true),
            ])
            ->values();

        return ApiResponse::success($regions);
    }

    /** Cities inside one region (drill-down for address pickers). */
    public function regionCities(string $regionPublicId): JsonResponse
    {
        $region = Region::query()
            ->where('public_id', $regionPublicId)
            ->firstOrFail();

        $rows = City::query()
            ->active()
            ->forRegion((int) $region->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(CityResource::collection($rows));
    }
}
