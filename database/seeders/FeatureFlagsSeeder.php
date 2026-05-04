<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Shared\Domain\Models\FeatureFlag;
use Illuminate\Database\Seeder;

class FeatureFlagsSeeder extends Seeder
{
    public function run(): void
    {
        $flags = [
            ['key' => 'loyalty_enabled',     'is_enabled' => false, 'rollout_pct' => 0,   'description' => 'Enable per-vendor loyalty programs'],
            ['key' => 'negotiations_enabled', 'is_enabled' => false, 'rollout_pct' => 0,   'description' => 'Allow customers to negotiate prices'],
            ['key' => 'digital_delivery',    'is_enabled' => true,  'rollout_pct' => 100, 'description' => 'Enable digital product instant delivery'],
        ];

        foreach ($flags as $flag) {
            FeatureFlag::firstOrCreate(
                ['key' => $flag['key']],
                [
                    'is_enabled' => $flag['is_enabled'],
                    'rollout_pct' => $flag['rollout_pct'],
                    'description' => $flag['description'],
                ],
            );
        }
    }
}
