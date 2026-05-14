<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Database\Factories;

use App\Modules\Subscriptions\Domain\Enums\BillingCycle;
use App\Modules\Subscriptions\Domain\Enums\SubscriptionStatus;
use App\Modules\Subscriptions\Domain\Models\SubscriptionPlan;
use App\Modules\Subscriptions\Domain\Models\VendorSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class VendorSubscriptionFactory extends Factory
{
    protected $model = VendorSubscription::class;

    public function definition(): array
    {
        $start = now()->subDays(15);

        return [
            'public_id' => (string) Str::ulid(),
            'subscription_plan_id' => SubscriptionPlan::factory(),
            'status' => SubscriptionStatus::Active->value,
            'billing_cycle' => BillingCycle::Monthly->value,
            'current_period_start' => $start,
            'current_period_end' => $start->copy()->addMonth(),
            'cancel_at_period_end' => false,
            'is_admin_override' => false,
            'started_at' => $start,
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => SubscriptionStatus::Active->value]);
    }

    public function pastDue(): static
    {
        return $this->state([
            'status' => SubscriptionStatus::PastDue->value,
            'grace_period_ends_at' => now()->addDays(5),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status' => SubscriptionStatus::Cancelled->value,
            'ended_at' => now()->subDay(),
        ]);
    }

    public function expired(): static
    {
        return $this->state([
            'status' => SubscriptionStatus::Expired->value,
            'ended_at' => now()->subDay(),
        ]);
    }

    public function withOverride(): static
    {
        return $this->state([
            'is_admin_override' => true,
            'override_reason' => 'Beta partner',
            'override_expires_at' => now()->addDays(30),
        ]);
    }
}
