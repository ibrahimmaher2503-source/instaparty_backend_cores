<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\Actions;

use App\Modules\Subscriptions\Application\Services\SubscriptionAuditWriter;
use App\Modules\Subscriptions\Domain\Enums\BillingCycle;
use App\Modules\Subscriptions\Domain\Enums\PlanCode;
use App\Modules\Subscriptions\Domain\Enums\SubscriptionEventType;
use App\Modules\Subscriptions\Domain\Enums\SubscriptionStatus;
use App\Modules\Subscriptions\Domain\Events\SubscriptionActivated;
use App\Modules\Subscriptions\Domain\Models\SubscriptionPlan;
use App\Modules\Subscriptions\Domain\Models\VendorSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class AutoEnrolFreeTierAction
{
    public function __construct(private readonly SubscriptionAuditWriter $audit) {}

    public function execute(int $vendorProfileId): VendorSubscription
    {
        $existing = VendorSubscription::where('vendor_profile_id', $vendorProfileId)
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::PastDue->value])
            ->where('is_admin_override', false)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $plan = SubscriptionPlan::where('plan_code', PlanCode::Free->value)->first();

        if ($plan === null) {
            throw new RuntimeException('Free subscription plan not seeded.');
        }

        return DB::transaction(function () use ($vendorProfileId, $plan): VendorSubscription {
            $subscription = VendorSubscription::create([
                'public_id'             => (string) Str::ulid(),
                'vendor_profile_id'     => $vendorProfileId,
                'subscription_plan_id'  => $plan->id,
                'status'                => SubscriptionStatus::Active->value,
                'billing_cycle'         => BillingCycle::None->value,
                'current_period_start'  => now(),
                'current_period_end'    => null,
                'cancel_at_period_end'  => false,
                'is_admin_override'     => false,
                'started_at'            => now(),
            ]);

            $subscription->setRelation('plan', $plan);

            $this->audit->write(
                subscription: $subscription,
                eventType: SubscriptionEventType::Activated,
                afterState: ['status' => SubscriptionStatus::Active->value, 'plan_code' => PlanCode::Free->value],
                data: ['reason' => 'auto_enrol_free'],
            );

            DB::afterCommit(fn () => event(new SubscriptionActivated($subscription, BillingCycle::None->value)));

            return $subscription;
        });
    }
}
