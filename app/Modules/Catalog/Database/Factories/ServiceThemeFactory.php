<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Domain\Models\ServiceTheme;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ServiceTheme>
 */
class ServiceThemeFactory extends Factory
{
    protected $model = ServiceTheme::class;

    public function definition(): array
    {
        return [
            'public_id'  => (string) Str::ulid(),
            'code'       => $this->faker->unique()->slug(2),
            'name'       => [
                'en' => $this->faker->words(2, true),
                'ar' => $this->faker->words(2, true),
            ],
            'icon_path'  => null,
            'is_active'  => true,
        ];
    }
}
