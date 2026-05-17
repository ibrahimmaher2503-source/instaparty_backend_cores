<?php

declare(strict_types=1);

namespace App\Modules\Geography\Http\Controllers;

use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Geography\Http\Resources\CityResource;
use App\Modules\Geography\Http\Resources\GovernorateResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
