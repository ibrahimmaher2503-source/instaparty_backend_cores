<?php

declare(strict_types=1);

namespace App\Modules\Geography\Database\Factories;

use App\Modules\Geography\Domain\Models\Country;
use App\Modules\Geography\Domain\Models\Governorate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Governorate>
 */
class GovernorateFactory extends Factory
{
    protected $model = Governorate::class;

    private static int $sequence = 1;

    public function definition(): array
    {
        $sequence = self::$sequence++;

        return [
            'public_id' => (string) Str::ulid(),
            'country_id' => Country::factory(),
            'name' => ['en' => fake()->city(), 'ar' => fake()->city()],
            'code' => 'EG-'.$this->alphaCode($sequence, 3),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    private function alphaCode(int $sequence, int $length): string
    {
        $sequence = max(1, $sequence);
        $code = '';

        for ($index = 0; $index < $length; $index++) {
            $code = chr(65 + (($sequence - 1) % 26)).$code;
            $sequence = intdiv($sequence - 1, 26) + 1;
        }

        return $code;
    }
}
