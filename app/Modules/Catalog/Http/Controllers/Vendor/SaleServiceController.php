<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Vendor;

use App\Modules\Catalog\Application\Actions\CreateSaleServiceAction;
use App\Modules\Catalog\Application\DTOs\CreateSaleServiceDTO;
use App\Modules\Catalog\Http\Requests\CreateSaleServiceRequest;
use App\Modules\Catalog\Http\Requests\UpdateSaleServiceRequest;
use App\Modules\Catalog\Http\Resources\SaleServiceResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class SaleServiceController
{
    public function store(CreateSaleServiceRequest $request, CreateSaleServiceAction $action): JsonResponse
    {
        $vendor = $request->user()->vendorProfile()->firstOrFail();
        $service = $action->execute(CreateSaleServiceDTO::fromRequest($request, $vendor->id));

        return ApiResponse::success(new SaleServiceResource($service), [], 201);
    }

    public function update(UpdateSaleServiceRequest $request, string $publicId): JsonResponse
    {
        return ApiResponse::error('Not implemented yet', 501);
    }
}
