<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\CategoryFieldSchema;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoryFieldSchema>
 */
class CategoryFieldSchemaFactory extends Factory
{
    protected $model = CategoryFieldSchema::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'product_type' => $this->faker->randomElement(['rental', 'sale', 'digital']),
            'field_key' => $this->faker->unique()->word(),
            'field_label' => [
                'en' => $this->faker->words(2, true),
                'ar' => $this->faker->words(2, true),
            ],
            'field_type' => $this->faker->randomElement(['text', 'number', 'boolean', 'select', 'multiselect', 'date']),
            'options' => null,
            'is_required' => false,
            'is_filterable' => false,
            'validation_rules' => null,
            'sort_order' => $this->faker->numberBetween(0, 50),
        ];
    }
}
