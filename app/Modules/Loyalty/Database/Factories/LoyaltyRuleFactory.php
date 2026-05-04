<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Database\Factories;

use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;
use App\Modules\Loyalty\Domain\Models\LoyaltyRule;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LoyaltyRule>
 */
class LoyaltyRuleFactory extends Factory
{
    protected $model = LoyaltyRule::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'loyalty_program_id' => LoyaltyProgram::factory(),
            'label' => [
                'en' => $this->faker->words(3, true),
                'ar' => 'قاعدة '.Str::title($this->faker->word()),
            ],
            'earn_points_per_minor' => 1,
            'earn_minor_per_unit' => 100,
            'redemption_ratio_points' => 100,
            'redemption_ratio_minor' => 1000,
            'min_points_to_redeem' => 0,
            'max_redeem_pct_bps' => 5000,
            'is_active' => true,
            'effective_from' => now(),
        ];
    }

    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
