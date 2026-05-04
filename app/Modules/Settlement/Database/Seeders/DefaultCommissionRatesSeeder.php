<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Database\Seeders;

use App\Modules\Settlement\Domain\Models\CommissionRate;
use App\Modules\Shared\Database\Seeders\Concerns\SeedsDevelopmentData;
use Illuminate\Database\Seeder;

final class DefaultCommissionRatesSeeder extends Seeder
{
    use SeedsDevelopmentData;

    public function run(): void
    {
        $this->updateOrCreateFactoryModel(
            CommissionRate::factory()->globalDefault()->make([
                'public_id' => $this->stablePublicId('commission-rate:global-default'),
                'category_id' => null,
                'product_type' => null,
                'commission_bps' => 1500,
                'effective_from' => now()->toDateString(),
            ]),
            ['category_id' => null, 'product_type' => null],
        );
    }
}
