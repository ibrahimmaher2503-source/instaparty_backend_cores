<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Vendor;

use App\Modules\Catalog\Application\Actions\CreateDigitalServiceAction;
use App\Modules\Catalog\Application\Actions\DetectMaterialServiceChangesAction;
use App\Modules\Catalog\Application\Actions\SubmitServiceChangeRequestAction;
use App\Modules\Catalog\Application\Actions\UpdateDigitalServiceAction;
use App\Modules\Catalog\Application\DTOs\CreateDigitalServiceDTO;
use App\Modules\Catalog\Application\DTOs\SubmitServiceChangeRequestDTO;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\States\ServiceStatus\PublishedState;
use App\Modules\Catalog\Http\Requests\CreateDigitalServiceRequest;
use App\Modules\Catalog\Http\Requests\UpdateDigitalServiceRequest;
use App\Modules\Catalog\Http\Resources\DigitalServiceResource;
use App\Modules\Catalog\Http\Resources\ServiceChangeRequestResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * @group Vendor - Services (Digital)
 */
class DigitalServiceController
{
    public function store(CreateDigitalServiceRequest $request, CreateDigitalServiceAction $action): JsonResponse
    {
        $vendor = $request->user()->vendorProfile()->firstOrFail();
        $service = $action->execute(CreateDigitalServiceDTO::fromRequest($request, $vendor->id));

        return ApiResponse::success(new DigitalServiceResource($service), [], 201);
    }

    public function update(
        UpdateDigitalServiceRequest $request,
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

        $updated = app(UpdateDigitalServiceAction::class)->execute($service, $vendor, $request->validated());

        return ApiResponse::success(new DigitalServiceResource($updated));
    }
}
