<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Domain\Models\ExcelImport;
use App\Modules\Catalog\Domain\Models\ExcelImportError;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExcelImportError>
 */
final class ExcelImportErrorFactory extends Factory
{
    protected $model = ExcelImportError::class;

    public function definition(): array
    {
        return [
            'excel_import_id' => ExcelImport::factory()->failed(),
            'row_number' => $this->faker->numberBetween(2, 50),
            'field' => $this->faker->randomElement(['name', 'price_minor', 'category_code', 'product_type']),
            'message' => [
                'en' => $this->faker->sentence(),
                'ar' => 'خطأ في بيانات الاستيراد.',
            ],
            'created_at' => now(),
        ];
    }
}
