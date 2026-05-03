<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Database\Factories;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Settlement\Domain\Enums\CommissionStatus;
use App\Modules\Settlement\Domain\Models\Commission;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Commission>
 */
class CommissionFactory extends Factory
{
    protected $model = Commission::class;

    public function definition(): array
    {
        $grossMinor = $this->faker->numberBetween(10000, 500000);
        $commissionBps = 1500; // 15%
        $commissionMinor = (int) round($grossMinor * $commissionBps / 10000);
        $vendorShareMinor = $grossMinor - $commissionMinor;

        return [
            'public_id' => (string) Str::ulid(),
            'booking_item_id' => 1, // overridden in tests
            'payment_id' => 1, // overridden in tests
            'vendor_profile_id' => 1, // overridden in tests
            'category_id' => null,
            'product_type' => ProductType::Rental,
            'gross_amount_minor' => $grossMinor,
            'gross_amount_currency' => 'EGP',
            'commission_bps' => $commissionBps,
            'commission_minor' => $commissionMinor,
            'commission_currency' => 'EGP',
            'vendor_share_minor' => $vendorShareMinor,
            'vendor_share_currency' => 'EGP',
            'reversed_amount_minor' => 0,
            'status' => CommissionStatus::Calculated,
        ];
    }

    public function rental(): static
    {
        return $this->state(['product_type' => ProductType::Rental]);
    }

    public function sale(): static
    {
        return $this->state(['product_type' => ProductType::Sale]);
    }

    public function digital(): static
    {
        return $this->state(['product_type' => ProductType::Digital]);
    }

    public function reversed(): static
    {
        return $this->state(function (array $attributes): array {
            return [
                'status' => CommissionStatus::Reversed,
                'reversed_amount_minor' => $attributes['commission_minor'],
            ];
        });
    }
}
