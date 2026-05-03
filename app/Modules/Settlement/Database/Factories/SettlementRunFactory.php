<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Database\Factories;

use App\Modules\Settlement\Domain\Enums\SettlementRunStatus;
use App\Modules\Settlement\Domain\Models\SettlementRun;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SettlementRun>
 */
class SettlementRunFactory extends Factory
{
    protected $model = SettlementRun::class;

    public function definition(): array
    {
        $periodStart = now()->startOfMonth()->toDateString();
        $periodEnd = now()->endOfMonth()->toDateString();
        $grossMinor = $this->faker->numberBetween(100000, 10000000);
        $commissionMinor = (int) round($grossMinor * 0.15);
        $vendorShareMinor = $grossMinor - $commissionMinor;

        return [
            'public_id' => (string) Str::ulid(),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'total_gross_minor' => $grossMinor,
            'total_gross_currency' => 'EGP',
            'total_commission_minor' => $commissionMinor,
            'total_commission_currency' => 'EGP',
            'total_vendor_share_minor' => $vendorShareMinor,
            'total_vendor_share_currency' => 'EGP',
            'status' => SettlementRunStatus::Pending,
        ];
    }

    public function reconciled(): static
    {
        return $this->state(['status' => SettlementRunStatus::Reconciled]);
    }

    public function disputed(): static
    {
        return $this->state(['status' => SettlementRunStatus::Disputed]);
    }
}
