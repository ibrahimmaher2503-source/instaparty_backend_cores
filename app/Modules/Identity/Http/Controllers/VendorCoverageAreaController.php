<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Geography\Domain\Models\City;
use App\Modules\Identity\Application\Actions\AddVendorCoverageAreaAction;
use App\Modules\Identity\Application\Actions\RemoveVendorCoverageAreaAction;
use App\Modules\Identity\Application\Actions\UpdateVendorCoverageAreaAction;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorCoverageArea;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Http\Requests\AddVendorCoverageAreaRequest;
use App\Modules\Identity\Http\Resources\VendorCoverageAreaResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class VendorCoverageAreaController extends Controller
{
    /** 9.1 — covered cities + fees. */
    public function index(Request $request): JsonResponse
    {
        $areas = VendorCoverageArea::query()
            ->where('vendor_profile_id', $this->vendorProfileForUser($request->user())->id)
            ->with('city')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(VendorCoverageAreaResource::collection($areas));
    }

    public function store(AddVendorCoverageAreaRequest $request, AddVendorCoverageAreaAction $action): JsonResponse
    {
        $vendorProfile = $this->vendorProfileForUser($request->user());
        $area = $action->execute($vendorProfile, $request->validated());

        return ApiResponse::success(new VendorCoverageAreaResource($area), [], 201);
    }

    /** 9.3 — update delivery fee / minimum for one covered city. */
    public function update(Request $request, int $cityId, UpdateVendorCoverageAreaAction $action): JsonResponse
    {
        $validated = $request->validate([
            'delivery_fee_minor' => ['sometimes', 'integer', 'min:0'],
            'min_order_minor' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);

        $area = $action->execute($this->ownedArea($request, $cityId), $validated);

        return ApiResponse::success(new VendorCoverageAreaResource($area));
    }

    /** 9.4 — stop serving a city. */
    public function destroy(Request $request, int $cityId, RemoveVendorCoverageAreaAction $action): JsonResponse
    {
        $action->execute($this->ownedArea($request, $cityId));

        return ApiResponse::success(null);
    }

    /** 9.5 — cities not yet covered by this vendor. */
    public function availableCities(Request $request): JsonResponse
    {
        $vendorProfile = $this->vendorProfileForUser($request->user());
        $locale = app()->getLocale();

        $cities = City::query()
            ->active()
            ->whereNotIn('id', VendorCoverageArea::query()
                ->where('vendor_profile_id', $vendorProfile->id)
                ->select('city_id'))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (City $city): array => [
                'id' => $city->id,
                'public_id' => $city->public_id,
                'name' => $city->getTranslation('name', $locale, useFallbackLocale: true),
            ])
            ->values();

        return ApiResponse::success($cities);
    }

    private function ownedArea(Request $request, int $cityId): VendorCoverageArea
    {
        return VendorCoverageArea::query()
            ->where('vendor_profile_id', $this->vendorProfileForUser($request->user())->id)
            ->where('city_id', $cityId)
            ->firstOrFail();
    }

    private function vendorProfileForUser(?User $user): VendorProfile
    {
        if ($user === null) {
            throw new NotFoundHttpException('Vendor profile not found.');
        }

        $vendorProfile = $user->vendorProfile;

        if ($vendorProfile === null) {
            throw new NotFoundHttpException('Vendor profile not found.');
        }

        return $vendorProfile;
    }
}
