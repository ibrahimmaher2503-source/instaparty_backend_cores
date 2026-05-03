<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Database\Factories;

use App\Modules\Settlement\Domain\Models\CommissionRate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CommissionRate>
 */
class CommissionRateFactory extends Factory
{
    protected $model = CommissionRate::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'category_id' => null,
            'product_type' => null,
            'commission_bps' => 1500, // 15% platform default
            'effective_from' => now()->toDateString(),
        ];
    }

    public function globalDefault(): static
    {
        return $this->state([
            'category_id' => null,
            'product_type' => null,
            'commission_bps' => 1500,
        ]);
    }
}
