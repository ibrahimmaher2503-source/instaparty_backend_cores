<?php

declare(strict_types=1);

namespace App\Modules\Geography\Database\Factories;

use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<City>
 */
class CityFactory extends Factory
{
    protected $model = City::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'region_id' => Region::factory(),
            'governorate_id' => fn (array $attrs) => Region::findOrFail($attrs['region_id'])->governorate_id,
            'name' => ['en' => fake()->city(), 'ar' => fake()->city()],
            'latitude' => fake()->randomFloat(7, 22, 31),
            'longitude' => fake()->randomFloat(7, 25, 35),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
