<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Http\Controllers\Customer;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Discovery\Application\Services\VendorAvailabilityService;
use App\Modules\Discovery\Http\Resources\ServiceSearchResultResource;
use App\Modules\Discovery\Infrastructure\Repositories\VendorBrowsingRepository;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use App\Modules\Shared\Http\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Customer vendor-browsing endpoints (audit F8 / file 17a). All public —
 * only approved vendors resolve (404 otherwise, no status leak).
 */
class VendorBrowsingController
{
    public function __construct(
        private readonly VendorBrowsingRepository $repository,
        private readonly VendorAvailabilityService $availability,
    ) {}

    public function services(Request $request, string $publicId): JsonResponse
    {
        $request->validate(['type' => ['nullable', 'string', 'in:rental,sale,digital']]);

        $page = $this->repository->servicesFor(
            $this->approvedVendor($publicId)->id,
            $request->filled('type') ? ProductType::from((string) $request->string('type')) : null,
        );

        return ApiResponse::success(
            ServiceSearchResultResource::collection($page->items()),
            ['next_cursor' => $page->nextCursor()?->encode(), 'has_more' => $page->hasMorePages()],
        );
    }

    public function coverage(Request $request, string $publicId): JsonResponse
    {
        $locale = app()->getLocale();
        $rows = $this->repository->coverageFor($this->approvedVendor($publicId)->id);

        return ApiResponse::success([
            'cities_count' => $rows->count(),
            'cities' => $rows->map(fn (object $r): array => [
                'public_id' => $r->city_public_id,
                'name' => json_decode((string) $r->city_name, true)[$locale]
                    ?? json_decode((string) $r->city_name, true)['en']
                    ?? null,
                'delivery_fee_minor' => $r->delivery_fee_minor !== null ? (int) $r->delivery_fee_minor : null,
                'delivery_fee_currency' => $r->delivery_fee_currency ?? 'EGP',
                'min_order_minor' => $r->min_order_minor !== null ? (int) $r->min_order_minor : null,
                'min_order_currency' => $r->min_order_currency ?? 'EGP',
            ])->values()->all(),
        ]);
    }

    public function availability(string $publicId): JsonResponse
    {
        return ApiResponse::success($this->availability->snapshotFor($this->approvedVendor($publicId)->id));
    }

    public function availabilityCheck(Request $request, string $publicId): JsonResponse
    {
        $request->validate(['date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today']]);

        return ApiResponse::success($this->availability->checkDate(
            $this->approvedVendor($publicId)->id,
            CarbonImmutable::parse((string) $request->string('date'), 'Africa/Cairo'),
        ));
    }

    public function portfolio(string $publicId): JsonResponse
    {
        return ApiResponse::success($this->repository->portfolioFor($this->approvedVendor($publicId)->id));
    }

    private function approvedVendor(string $publicId): VendorProfile
    {
        $vendor = VendorProfile::query()
            ->whereState('approval_status', ApprovedState::class)
            ->where('public_id', $publicId)
            ->first();

        abort_if($vendor === null, 404, 'Vendor not found.');

        return $vendor;
    }
}
