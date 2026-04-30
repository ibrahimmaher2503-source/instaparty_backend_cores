<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Vendor;

use App\Modules\Catalog\Application\Actions\CreateDigitalServiceAction;
use App\Modules\Catalog\Application\DTOs\CreateDigitalServiceDTO;
use App\Modules\Catalog\Http\Requests\CreateDigitalServiceRequest;
use App\Modules\Catalog\Http\Requests\UpdateDigitalServiceRequest;
use App\Modules\Catalog\Http\Resources\DigitalServiceResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class DigitalServiceController
{
    public function store(CreateDigitalServiceRequest $request, CreateDigitalServiceAction $action): JsonResponse
    {
        $vendor = $request->user()->vendorProfile()->firstOrFail();
        $service = $action->execute(CreateDigitalServiceDTO::fromRequest($request, $vendor->id));

        return ApiResponse::success(new DigitalServiceResource($service), [], 201);
    }

    public function update(UpdateDigitalServiceRequest $request, string $publicId): JsonResponse
    {
        return ApiResponse::error('Not implemented yet', 501);
    }
}
