<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Application\Actions\RequestDigitalServiceChangesAction;
use App\Modules\Catalog\Application\Actions\RequestRentalServiceChangesAction;
use App\Modules\Catalog\Application\Actions\RequestSaleServiceChangesAction;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Http\Requests\RequestServiceChangesFormRequest;
use Illuminate\Http\JsonResponse;

class ServiceChangeRequestController
{
    public function store(
        RequestServiceChangesFormRequest $request,
        Service $service,
    ): JsonResponse {
        $action = match ($service->product_type) {
            ProductType::Rental => app(RequestRentalServiceChangesAction::class),
            ProductType::Sale => app(RequestSaleServiceChangesAction::class),
            ProductType::Digital => app(RequestDigitalServiceChangesAction::class),
        };

        $changeRequest = $action->execute(
            $service,
            $request->validated('items'),
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
        ], 201);
    }
}
