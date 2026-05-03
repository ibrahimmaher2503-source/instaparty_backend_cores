<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Domain\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'parent_id' => null,
            'code' => $this->faker->unique()->slug(2),
            'name' => [
                'en' => $this->faker->words(2, true),
                'ar' => $this->faker->words(2, true),
            ],
            'description' => [
                'en' => $this->faker->sentence(),
                'ar' => $this->faker->sentence(),
            ],
            'icon_path' => null,
            'allowed_product_types' => ['rental'],
            'sort_order' => $this->faker->numberBetween(0, 100),
            'is_active' => true,
        ];
    }
}
