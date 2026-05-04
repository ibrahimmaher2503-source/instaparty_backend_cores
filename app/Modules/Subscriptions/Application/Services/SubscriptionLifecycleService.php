<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\Services;

use App\Modules\Subscriptions\Domain\Enums\SubscriptionEventType;
use App\Modules\Subscriptions\Domain\Enums\SubscriptionStatus;
use App\Modules\Subscriptions\Domain\Models\VendorSubscription;
use App\Modules\Subscriptions\Domain\States\PastDueState;
use Illuminate\Support\Facades\DB;

class SubscriptionLifecycleService
{
    public function __construct(
        private readonly SubscriptionAuditWriter $auditWriter,
    ) {}

    /** Transition subscription to past_due and set grace window. Must be called inside DB::transaction. */
    public function enterGrace(VendorSubscription $subscription, int $graceDays = 7): void
    {
        $before = ['status' => $subscription->status->getValue()];

        $subscription->status->transitionTo(PastDueState::class);
        $subscription->grace_period_ends_at = now()->addDays($graceDays);
        $subscription->save();

        $this->auditWriter->write(
            subscription: $subscription,
            eventType: SubscriptionEventType::PastDue,
            beforeState: $before,
            afterState: ['status' => 'past_due', 'grace_period_ends_at' => $subscription->grace_period_ends_at?->toISOString()],
            data: ['grace_period_ends_at' => $subscription->grace_period_ends_at?->toISOString()],
        );
    }

    /** Transition to expired and nullify period end. Must be called inside DB::transaction. */
    public function expire(VendorSubscription $subscription): void
    {
        $before = ['status' => $subscription->status->getValue()];

        $subscription->status = SubscriptionStatus::Expired->value;
        $subscription->ended_at = now();
        $subscription->ended_reason = 'grace_expired';
        $subscription->save();

        $this->auditWriter->write(
            subscription: $subscription,
            eventType: SubscriptionEventType::Expired,
            beforeState: $before,
            afterState: ['status' => 'expired'],
        );
    }
}
