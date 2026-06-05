<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\DTOs\ServiceAvailabilityResultDTO;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ReservationStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceInventoryReservation;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Read-only availability probe. Places NO hold — inventory is reserved at
 * booking submit via {@see HoldServiceInventoryAction} (cart hold 15 min,
 * payment hold 24 h). Mirrors that action's per-type predicates exactly so a
 * "true" here means a submit at the same instant would have succeeded.
 */
class CheckServiceAvailabilityAction
{
    public function execute(
        Service $service,
        ?Carbon $startsAt = null,
        ?Carbon $endsAt = null,
        int $quantity = 1,
    ): ServiceAvailabilityResultDTO {
        return match ($service->product_type) {
            ProductType::Rental => $this->checkRental($service, $startsAt, $endsAt),
            ProductType::Sale => $this->checkSale($service, $quantity),
            ProductType::Digital => $this->checkDigital($service),
        };
    }

    private function checkRental(Service $service, ?Carbon $startsAt, ?Carbon $endsAt): ServiceAvailabilityResultDTO
    {
        if ($startsAt === null || $endsAt === null) {
            throw ValidationException::withMessages([
                'starts_at' => [__('catalog::catalog.availability.window_required')],
            ]);
        }

        $hasOverlap = ServiceInventoryReservation::query()
            ->where('service_id', $service->id)
            ->whereIn('status', [ReservationStatus::Held->value, ReservationStatus::Confirmed->value])
            ->whereNotNull('reserved_starts_at')
            ->where('reserved_starts_at', '<', $endsAt)
            ->where('reserved_ends_at', '>', $startsAt)
            ->exists();

        return new ServiceAvailabilityResultDTO(
            available: ! $hasOverlap,
            productType: ProductType::Rental,
            reasonCode: $hasOverlap ? 'window_unavailable' : null,
            remainingQuantity: null,
            startsAt: $startsAt,
            endsAt: $endsAt,
        );
    }

    private function checkSale(Service $service, int $quantity): ServiceAvailabilityResultDTO
    {
        $stockQuantity = $service->saleDetail?->stock_quantity;

        // NULL stock means made-to-order: no inventory ceiling applies.
        if ($stockQuantity === null) {
            return new ServiceAvailabilityResultDTO(
                available: true,
                productType: ProductType::Sale,
                reasonCode: null,
                remainingQuantity: null,
                startsAt: null,
                endsAt: null,
            );
        }

        $currentlyReserved = (int) ServiceInventoryReservation::query()
            ->where('service_id', $service->id)
            ->whereIn('status', [ReservationStatus::Held->value, ReservationStatus::Confirmed->value])
            ->sum('quantity');

        $remaining = max(0, $stockQuantity - $currentlyReserved);

        return new ServiceAvailabilityResultDTO(
            available: $remaining >= $quantity,
            productType: ProductType::Sale,
            reasonCode: $remaining >= $quantity ? null : 'insufficient_stock',
            remainingQuantity: $remaining,
            startsAt: null,
            endsAt: null,
        );
    }

    private function checkDigital(Service $service): ServiceAvailabilityResultDTO
    {
        // Digital services have no stock or time constraints.
        return new ServiceAvailabilityResultDTO(
            available: true,
            productType: ProductType::Digital,
            reasonCode: null,
            remainingQuantity: null,
            startsAt: null,
            endsAt: null,
        );
    }
}
