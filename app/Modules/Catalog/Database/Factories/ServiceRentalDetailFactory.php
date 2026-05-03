<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Domain\Models\ServiceRentalDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceRentalDetail>
 */
class ServiceRentalDetailFactory extends Factory
{
    protected $model = ServiceRentalDetail::class;

    public function definition(): array
    {
        return [
            'requires_electricity' => $this->faker->boolean(),
            'requires_outdoor_space' => $this->faker->boolean(),
            'default_rental_duration_hours' => $this->faker->numberBetween(2, 8),
            'setup_time_minutes' => $this->faker->numberBetween(30, 120),
            'teardown_time_minutes' => $this->faker->numberBetween(30, 120),
            'security_deposit_minor' => $this->faker->numberBetween(5000, 50000),
            'security_deposit_currency' => 'EGP',
            'minimum_space_sqm' => $this->faker->numberBetween(10, 100),
        ];
    }
}
