<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Database\Factories;

use App\Modules\Subscriptions\Domain\Enums\PlanCode;
use App\Modules\Subscriptions\Domain\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        $code = $this->faker->randomElement(PlanCode::cases());

        return [
            'public_id' => (string) Str::ulid(),
            'plan_code' => $code->value,
            'name' => ['en' => $code->label(), 'ar' => $code->label()],
            'description' => ['en' => $this->faker->sentence(), 'ar' => $this->faker->sentence()],
            'monthly_price_minor' => $code === PlanCode::Free ? 0 : $this->faker->numberBetween(9900, 99900),
            'monthly_price_currency' => 'EGP',
            'yearly_price_minor' => $code === PlanCode::Free ? 0 : $this->faker->numberBetween(99000, 999000),
            'yearly_price_currency' => 'EGP',
            'is_default' => $code === PlanCode::Free,
            'is_published' => true,
            'display_order' => match ($code) {
                PlanCode::Free => 1,
                PlanCode::Silver => 2,
                PlanCode::Gold => 3,
                PlanCode::Premium => 4,
            },
        ];
    }

    public function free(): static
    {
        return $this->state(['plan_code' => PlanCode::Free->value, 'is_default' => true, 'monthly_price_minor' => 0, 'yearly_price_minor' => 0]);
    }
}
