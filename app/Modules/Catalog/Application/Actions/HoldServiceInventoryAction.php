<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Enums\HoldType;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ReservationStatus;
use App\Modules\Catalog\Domain\Exceptions\InventoryNotAvailableException;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceInventoryReservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HoldServiceInventoryAction
{
    public function execute(
        Service $service,
        HoldType $holdType,
        int $userId,
        ?Carbon $startsAt = null,
        ?Carbon $endsAt = null,
        int $quantity = 1,
    ): ServiceInventoryReservation {
        return DB::transaction(function () use ($service, $holdType, $userId, $startsAt, $endsAt, $quantity): ServiceInventoryReservation {
            return match ($service->product_type) {
                ProductType::Rental => $this->holdRental($service, $holdType, $userId, $startsAt, $endsAt),
                ProductType::Sale => $this->holdSale($service, $holdType, $userId, $quantity),
                ProductType::Digital => $this->holdDigital($service, $holdType, $userId),
            };
        });
    }

    private function holdRental(
        Service $service,
        HoldType $holdType,
        int $userId,
        ?Carbon $startsAt,
        ?Carbon $endsAt,
    ): ServiceInventoryReservation {
        if ($startsAt === null || $endsAt === null) {
            throw new \InvalidArgumentException('startsAt and endsAt are required for rental inventory holds.');
        }

        $hasOverlap = ServiceInventoryReservation::query()
            ->lockForUpdate()
            ->where('service_id', $service->id)
            ->whereIn('status', [ReservationStatus::Held->value, ReservationStatus::Confirmed->value])
            ->whereNotNull('reserved_starts_at')
            ->where('reserved_starts_at', '<', $endsAt)
            ->where('reserved_ends_at', '>', $startsAt)
            ->exists();

        if ($hasOverlap) {
            throw new InventoryNotAvailableException(
                "Service {$service->public_id} is not available for the requested time window."
            );
        }

        return ServiceInventoryReservation::create([
            'public_id' => Str::ulid()->toBase32(),
            'service_id' => $service->id,
            'user_id' => $userId,
            'product_type' => ProductType::Rental,
            'hold_type' => $holdType,
            'status' => ReservationStatus::Held,
            'reserved_starts_at' => $startsAt,
            'reserved_ends_at' => $endsAt,
            'quantity' => 1,
            'expires_at' => now()->addMinutes($holdType->ttlMinutes()),
        ]);
    }

    private function holdSale(
        Service $service,
        HoldType $holdType,
        int $userId,
        int $quantity,
    ): ServiceInventoryReservation {
        $stockQuantity = $service->saleDetail?->stock_quantity;

        if ($stockQuantity !== null) {
            // Lock rows for this service to prevent race conditions
            $currentlyReserved = (int) ServiceInventoryReservation::query()
                ->lockForUpdate()
                ->where('service_id', $service->id)
                ->whereIn('status', [ReservationStatus::Held->value, ReservationStatus::Confirmed->value])
                ->sum('quantity');

            if ($currentlyReserved + $quantity > $stockQuantity) {
                throw new InventoryNotAvailableException(
                    "Service {$service->public_id} does not have sufficient stock for the requested quantity."
                );
            }
        }

        return ServiceInventoryReservation::create([
            'public_id' => Str::ulid()->toBase32(),
            'service_id' => $service->id,
            'user_id' => $userId,
            'product_type' => ProductType::Sale,
            'hold_type' => $holdType,
            'status' => ReservationStatus::Held,
            'quantity' => $quantity,
            'expires_at' => now()->addMinutes($holdType->ttlMinutes()),
        ]);
    }

    private function holdDigital(
        Service $service,
        HoldType $holdType,
        int $userId,
    ): ServiceInventoryReservation {
        // Digital services have no stock or time constraints — always create the hold
        return ServiceInventoryReservation::create([
            'public_id' => Str::ulid()->toBase32(),
            'service_id' => $service->id,
            'user_id' => $userId,
            'product_type' => ProductType::Digital,
            'hold_type' => $holdType,
            'status' => ReservationStatus::Held,
            'quantity' => 1,
            'expires_at' => now()->addMinutes($holdType->ttlMinutes()),
        ]);
    }
}
