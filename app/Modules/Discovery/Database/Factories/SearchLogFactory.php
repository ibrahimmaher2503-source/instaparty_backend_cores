<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Database\Factories;

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Discovery\Domain\Models\SearchLog;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SearchLog>
 */
class SearchLogFactory extends Factory
{
    protected $model = SearchLog::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->asCustomer(),
            'query' => $this->faker->words(3, true),
            'locale' => $this->faker->randomElement(['en', 'ar']),
            'filters' => [
                'product_types' => ['sale'],
            ],
            'results_count' => $this->faker->numberBetween(0, 50),
            'clicked_service_id' => null,
            'created_at' => now(),
        ];
    }

    public function clicked(): static
    {
        return $this->state([
            'clicked_service_id' => Service::factory()->published(),
        ]);
    }
}
