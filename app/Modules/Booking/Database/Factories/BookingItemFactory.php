<?php

declare(strict_types=1);

namespace App\Modules\Booking\Database\Factories;

use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BookingItem>
 */
class BookingItemFactory extends Factory
{
    protected $model = BookingItem::class;

    public function definition(): array
    {
        $productType = $this->faker->randomElement(ProductType::cases());

        return [
            'public_id' => (string) Str::ulid(),
            'booking_vendor_id' => BookingVendor::factory(),
            'service_id' => match ($productType) {
                ProductType::Rental => Service::factory()->rental(),
                ProductType::Sale => Service::factory()->sale(),
                ProductType::Digital => Service::factory()->digital(),
            },
            'product_type' => $productType,
            'name_snapshot' => [
                'en' => $this->faker->words(3, true),
                'ar' => 'عنصر '.Str::title($this->faker->word()),
            ],
            'unit_price_minor' => $this->faker->numberBetween(10000, 500000),
            'unit_price_currency' => 'EGP',
            'line_total_minor' => $this->faker->numberBetween(10000, 500000),
            'line_total_currency' => 'EGP',
            'commission_minor' => $this->faker->numberBetween(1000, 50000),
            'commission_currency' => 'EGP',
            'quantity' => $this->faker->numberBetween(1, 3),
            'effective_starts_at' => now()->addDays(14),
            'effective_ends_at' => now()->addDays(14)->addHours(4),
            'has_item_slot_override' => false,
            'customization_data' => [],
            'type_snapshot' => [],
            'fulfillment_data' => [],
            'item_status' => 'pending',
            'commission_bps' => 1500,
        ];
    }

    public function rental(): static
    {
        return $this->state(fn (): array => [
            'product_type' => ProductType::Rental,
            'service_id' => Service::factory()->rental(),
            'item_status' => 'confirmed',
        ]);
    }

    public function sale(): static
    {
        return $this->state(fn (): array => [
            'product_type' => ProductType::Sale,
            'service_id' => Service::factory()->sale(),
            'item_status' => 'pending',
        ]);
    }

    public function digital(): static
    {
        return $this->state(fn (): array => [
            'product_type' => ProductType::Digital,
            'service_id' => Service::factory()->digital(),
            'item_status' => 'sent',
        ]);
    }
}
