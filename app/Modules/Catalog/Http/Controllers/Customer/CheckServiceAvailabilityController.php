<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Customer;

use App\Modules\Catalog\Application\Actions\CheckServiceAvailabilityAction;
use App\Modules\Catalog\Application\Actions\ShowPublishedServiceAction;
use App\Modules\Catalog\Http\Requests\CheckServiceAvailabilityRequest;
use App\Modules\Shared\Http\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class CheckServiceAvailabilityController
{
    /**
     * @group Customer - Catalog
     *
     * Check a published service's availability without placing a hold.
     * Inventory is only reserved at booking submit (cart hold 15 min,
     * payment hold 24 h).
     *
     * @response {
     *   "data": {
     *     "available": true,
     *     "product_type": "rental",
     *     "reason_code": null,
     *     "remaining_quantity": null,
     *     "window": {"starts_at": "2026-07-01T10:00:00+00:00", "ends_at": "2026-07-01T15:00:00+00:00"},
     *     "reserved_at": "submit"
     *   }
     * }
     */
    public function __invoke(
        CheckServiceAvailabilityRequest $request,
        string $servicePublicId,
        ShowPublishedServiceAction $show,
        CheckServiceAvailabilityAction $check,
    ): JsonResponse {
        $result = $check->execute(
            $show->execute($servicePublicId),
            $request->filled('starts_at') ? Carbon::parse($request->string('starts_at')->toString())->utc() : null,
            $request->filled('ends_at') ? Carbon::parse($request->string('ends_at')->toString())->utc() : null,
            $request->integer('quantity', 1),
        );

        return ApiResponse::success([
            'available' => $result->available,
            'product_type' => $result->productType->value,
            'reason_code' => $result->reasonCode,
            'remaining_quantity' => $result->remainingQuantity,
            'window' => $result->startsAt !== null ? [
                'starts_at' => $result->startsAt->toIso8601String(),
                'ends_at' => $result->endsAt?->toIso8601String(),
            ] : null,
            'reserved_at' => 'submit',
        ]);
    }
}
