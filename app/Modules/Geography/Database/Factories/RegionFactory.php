<?php

declare(strict_types=1);

namespace App\Modules\Geography\Database\Factories;

use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Geography\Domain\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Region>
 */
class RegionFactory extends Factory
{
    protected $model = Region::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'governorate_id' => Governorate::factory(),
            'name' => ['en' => fake()->city(), 'ar' => fake()->city()],
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
