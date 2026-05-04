<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Geography\Domain\Models\City;
use App\Modules\Identity\Domain\Models\VendorCoverageArea;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorCoverageArea>
 */
class VendorCoverageAreaFactory extends Factory
{
    protected $model = VendorCoverageArea::class;

    public function definition(): array
    {
        return [
            'vendor_profile_id' => VendorProfile::factory()->approved(),
            'city_id' => City::factory(),
            'delivery_fee_minor' => $this->faker->numberBetween(2500, 10000),
            'delivery_fee_currency' => 'EGP',
            'min_order_minor' => $this->faker->numberBetween(30000, 100000),
            'min_order_currency' => 'EGP',
        ];
    }
}
