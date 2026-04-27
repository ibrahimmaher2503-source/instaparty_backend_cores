<?php

declare(strict_types=1);

namespace App\Modules\Geography\Database\Factories;

use App\Modules\Geography\Domain\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    protected $model = Country::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'name' => ['en' => fake()->country(), 'ar' => fake()->country()],
            'iso2' => strtoupper(fake()->unique()->lexify('??')),
            'iso3' => strtoupper(fake()->unique()->lexify('???')),
            'default_currency' => 'EGP',
            'default_locale' => 'ar',
            'default_timezone' => 'Africa/Cairo',
            'phone_code' => '+20',
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
