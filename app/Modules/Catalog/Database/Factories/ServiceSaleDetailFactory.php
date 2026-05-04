<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceSaleDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceSaleDetail>
 */
class ServiceSaleDetailFactory extends Factory
{
    protected $model = ServiceSaleDetail::class;

    public function definition(): array
    {
        return [
            'service_id' => Service::factory()->sale(),
            'is_perishable' => $this->faker->boolean(),
            'is_made_to_order' => $this->faker->boolean(),
            'lead_time_hours' => $this->faker->numberBetween(24, 168),
            'stock_quantity' => $this->faker->numberBetween(1, 100),
            'customization_fields' => [],
        ];
    }
}
