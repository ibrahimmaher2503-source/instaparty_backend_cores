<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Vendor;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Http\Resources\DigitalServiceResource;
use App\Modules\Catalog\Http\Resources\RentalServiceResource;
use App\Modules\Catalog\Http\Resources\SaleServiceResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Vendor - Services
 */
class VendorServiceShowController
{
    public function __invoke(Request $request, string $publicId): JsonResponse
    {
        $vendor = $request->user()->vendorProfile()->firstOrFail();

        $service = Service::query()
            ->where('public_id', $publicId)
            ->where('vendor_profile_id', $vendor->id)
            ->firstOrFail();

        return ApiResponse::success(match ($service->product_type) {
            ProductType::Rental => new RentalServiceResource($service->load('rentalDetail')),
            ProductType::Sale => new SaleServiceResource($service->load('saleDetail')),
            ProductType::Digital => new DigitalServiceResource($service->load('digitalDetail')),
        });
    }
}
