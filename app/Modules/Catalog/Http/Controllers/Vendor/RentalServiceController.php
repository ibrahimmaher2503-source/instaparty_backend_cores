<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Vendor;

use App\Modules\Catalog\Application\Actions\CreateRentalServiceAction;
use App\Modules\Catalog\Application\Actions\DetectMaterialServiceChangesAction;
use App\Modules\Catalog\Application\Actions\SubmitServiceChangeRequestAction;
use App\Modules\Catalog\Application\Actions\UpdateRentalServiceAction;
use App\Modules\Catalog\Application\DTOs\CreateRentalServiceDTO;
use App\Modules\Catalog\Application\DTOs\SubmitServiceChangeRequestDTO;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\States\ServiceStatus\PublishedState;
use App\Modules\Catalog\Http\Requests\CreateRentalServiceRequest;
use App\Modules\Catalog\Http\Requests\UpdateRentalServiceRequest;
use App\Modules\Catalog\Http\Resources\RentalServiceResource;
use App\Modules\Catalog\Http\Resources\ServiceChangeRequestResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * @group Vendor - Services (Rental)
 */
class RentalServiceController
{
    public function store(CreateRentalServiceRequest $request, CreateRentalServiceAction $action): JsonResponse
    {
        $vendor = $request->user()->vendorProfile()->firstOrFail();
        $service = $action->execute(CreateRentalServiceDTO::fromRequest($request, $vendor->id));

        return ApiResponse::success(new RentalServiceResource($service), [], 201);
    }

    public function update(
        UpdateRentalServiceRequest $request,
        string $publicId,
        DetectMaterialServiceChangesAction $detectAction,
        SubmitServiceChangeRequestAction $submitAction,
    ): JsonResponse {
        $vendor = $request->user()->vendorProfile()->firstOrFail();

        $service = Service::query()
            ->where('public_id', $publicId)
            ->where('vendor_profile_id', $vendor->id)
            ->firstOrFail();

        // Only stage edits on published services with material changes
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

        $updated = app(UpdateRentalServiceAction::class)->execute($service, $vendor, $request->validated());

        return ApiResponse::success(new RentalServiceResource($updated));
    }
}
