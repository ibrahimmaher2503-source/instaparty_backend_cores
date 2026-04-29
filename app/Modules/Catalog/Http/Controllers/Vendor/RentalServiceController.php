<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Vendor;

use App\Modules\Catalog\Application\Actions\CreateRentalServiceAction;
use App\Modules\Catalog\Application\DTOs\CreateRentalServiceDTO;
use App\Modules\Catalog\Http\Requests\CreateRentalServiceRequest;
use App\Modules\Catalog\Http\Requests\UpdateRentalServiceRequest;
use App\Modules\Catalog\Http\Resources\RentalServiceResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class RentalServiceController
{
    public function store(CreateRentalServiceRequest $request, CreateRentalServiceAction $action): JsonResponse
    {
        $vendor = $request->user()->vendorProfile;
        $service = $action->execute(CreateRentalServiceDTO::fromRequest($request, $vendor->id));

        return ApiResponse::success(new RentalServiceResource($service), [], 201);
    }

    public function update(UpdateRentalServiceRequest $request, string $publicId): JsonResponse
    {
        return ApiResponse::error('Not implemented yet', 501);
    }
}
