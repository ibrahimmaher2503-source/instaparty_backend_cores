<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\ExcelImport;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ExcelImport>
 */
final class ExcelImportFactory extends Factory
{
    protected $model = ExcelImport::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(ProductType::cases());
        $totalRows = $this->faker->numberBetween(3, 25);
        $errorRows = $this->faker->numberBetween(0, 3);

        return [
            'public_id' => (string) Str::ulid(),
            'vendor_profile_id' => VendorProfile::factory()->approved(),
            'product_type' => $type,
            'status' => 'pending',
            'original_filename' => $type->value.'-services.xlsx',
            'stored_path' => 'imports/development/'.Str::ulid().'.xlsx',
            'total_rows' => $totalRows,
            'imported_rows' => max(0, $totalRows - $errorRows),
            'error_rows' => $errorRows,
        ];
    }

    public function processing(): static
    {
        return $this->state(['status' => 'processing']);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'completed',
            'imported_rows' => $attributes['total_rows'],
            'error_rows' => 0,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'failed',
            'imported_rows' => max(0, (int) $attributes['total_rows'] - 1),
            'error_rows' => max(1, (int) $attributes['error_rows']),
        ]);
    }
}
