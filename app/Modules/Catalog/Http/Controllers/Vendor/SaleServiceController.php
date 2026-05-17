<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Vendor;

use App\Modules\Catalog\Application\Actions\CreateSaleServiceAction;
use App\Modules\Catalog\Application\Actions\DetectMaterialServiceChangesAction;
use App\Modules\Catalog\Application\Actions\SubmitServiceChangeRequestAction;
use App\Modules\Catalog\Application\DTOs\CreateSaleServiceDTO;
use App\Modules\Catalog\Application\DTOs\SubmitServiceChangeRequestDTO;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\States\ServiceStatus\PublishedState;
use App\Modules\Catalog\Http\Requests\CreateSaleServiceRequest;
use App\Modules\Catalog\Http\Requests\UpdateSaleServiceRequest;
use App\Modules\Catalog\Http\Resources\SaleServiceResource;
use App\Modules\Catalog\Http\Resources\ServiceChangeRequestResource;
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

    public function update(
        UpdateSaleServiceRequest $request,
        string $publicId,
        DetectMaterialServiceChangesAction $detectAction,
        SubmitServiceChangeRequestAction $submitAction,
    ): JsonResponse {
        $vendor = $request->user()->vendorProfile()->firstOrFail();

        $service = Service::query()
            ->where('public_id', $publicId)
            ->where('vendor_profile_id', $vendor->id)
            ->firstOrFail();

        if ($service->status instanceof PublishedState) {
            $diff = $detectAction->execute($service, $request->validated());

            if ($diff->hasMaterialChanges()) {
                $cr = $submitAction->execute(
                    new SubmitServiceChangeRequestDTO(
                        serviceId: $service->id,
                        vendorUserId: $request->user()->id,
                        vendorProfileId: $vendor->id,
                        proposedPayload: $request->validated(),
                        vendorNote: $request->input('vendor_note'),
                        idempotencyKey: $request->header('Idempotency-Key'),
                    ),
                    $diff,
                );

                return (new ServiceChangeRequestResource($cr))
                    ->response()
                    ->setStatusCode(202);
            }
        }

        return ApiResponse::error('Direct update not implemented yet', 501);
    }
}
