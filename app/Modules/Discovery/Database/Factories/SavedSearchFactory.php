<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Database\Factories;

use App\Modules\Discovery\Domain\Models\SavedSearch;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedSearch>
 */
class SavedSearchFactory extends Factory
{
    protected $model = SavedSearch::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->asCustomer(),
            'label' => $this->faker->words(3, true),
            'filters' => [
                'product_types' => ['rental'],
                'max_price_minor' => 500000,
            ],
        ];
    }
}
