<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Database\Factories;

use App\Modules\Loyalty\Domain\Enums\RuleKind;
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
            'rule_kind' => RuleKind::FirstBooking,
            'multiplier' => 2.00,
            'conditions' => null,
            'label' => [
                'en' => $this->faker->words(3, true),
                'ar' => 'قاعدة '.Str::title($this->faker->word()),
            ],
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }

    public function firstBooking(): static
    {
        return $this->state(['rule_kind' => RuleKind::FirstBooking]);
    }

    public function categoryBonus(int $categoryId = 1): static
    {
        return $this->state([
            'rule_kind' => RuleKind::CategoryBonus,
            'conditions' => ['category_id' => $categoryId],
        ]);
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
