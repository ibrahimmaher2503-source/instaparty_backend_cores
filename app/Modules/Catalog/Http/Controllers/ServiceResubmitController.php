<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Application\Actions\RequestDigitalServiceResubmitAction;
use App\Modules\Catalog\Application\Actions\RequestRentalServiceResubmitAction;
use App\Modules\Catalog\Application\Actions\RequestSaleServiceResubmitAction;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Http\Requests\ServiceResubmitFormRequest;
use Illuminate\Http\JsonResponse;

class ServiceResubmitController
{
    public function store(
        ServiceResubmitFormRequest $request,
        Service $service,
    ): JsonResponse {
        $action = match ($service->product_type) {
            ProductType::Rental => app(RequestRentalServiceResubmitAction::class),
            ProductType::Sale => app(RequestSaleServiceResubmitAction::class),
            ProductType::Digital => app(RequestDigitalServiceResubmitAction::class),
            null => abort(422, 'Service product_type is not set'),
        };

        $changeRequest = $action->execute(
            $service,
            $request->getChangedFields(),
            auth()->user(),
            $request->header('Idempotency-Key'),
        );

        return response()->json([
            'data' => [
                'id' => $changeRequest->id,
                'public_id' => $changeRequest->public_id ?? null,
                'subject_type' => $changeRequest->subject_type,
                'subject_id' => $changeRequest->subject_id,
                'status' => $changeRequest->status,
                'cycle_number' => $changeRequest->cycle_number,
            ],
        ], 200);
    }
}
