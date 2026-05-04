<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Domain\Enums\HoldType;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ReservationStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceInventoryReservation;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ServiceInventoryReservation>
 */
class ServiceInventoryReservationFactory extends Factory
{
    protected $model = ServiceInventoryReservation::class;

    public function definition(): array
    {
        $productType = $this->faker->randomElement(ProductType::cases());
        $start = now()->addDays($this->faker->numberBetween(1, 30));
        $end = (clone $start)->addHours($this->faker->numberBetween(2, 8));

        return [
            'public_id' => (string) Str::ulid(),
            'service_id' => match ($productType) {
                ProductType::Rental => Service::factory()->rental(),
                ProductType::Sale => Service::factory()->sale(),
                ProductType::Digital => Service::factory()->digital(),
            },
            'user_id' => User::factory(),
            'product_type' => $productType,
            'hold_type' => HoldType::Cart,
            'status' => ReservationStatus::Held,
            'reserved_starts_at' => $start,
            'reserved_ends_at' => $end,
            'quantity' => 1,
            'expires_at' => now()->addMinutes(HoldType::Cart->ttlMinutes()),
            'booking_item_id' => null,
        ];
    }

    public function paymentHold(): static
    {
        return $this->state([
            'hold_type' => HoldType::Payment,
            'expires_at' => now()->addMinutes(HoldType::Payment->ttlMinutes()),
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(['status' => ReservationStatus::Confirmed]);
    }

    public function expired(): static
    {
        return $this->state([
            'status' => ReservationStatus::Held,
            'expires_at' => now()->subMinutes(5),
        ]);
    }
}
