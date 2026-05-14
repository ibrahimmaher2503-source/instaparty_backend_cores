<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\Actions;

use App\Modules\Subscriptions\Application\Services\SubscriptionAuditWriter;
use App\Modules\Subscriptions\Domain\Enums\SubscriptionEventType;
use App\Modules\Subscriptions\Domain\Enums\SubscriptionStatus;
use App\Modules\Subscriptions\Domain\Events\AdminOverrideApplied;
use App\Modules\Subscriptions\Domain\Models\SubscriptionPlan;
use App\Modules\Subscriptions\Domain\Models\VendorSubscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApplyAdminTierOverrideAction
{
    public function __construct(
        private SubscriptionAuditWriter $auditWriter,
    ) {}

    public function execute(
        int $vendorProfileId,
        SubscriptionPlan $plan,
        string $reasonEn,
        string $reasonAr,
        ?Carbon $expiresAt = null,
    ): VendorSubscription {
        return DB::transaction(function () use ($vendorProfileId, $plan, $reasonEn, $reasonAr, $expiresAt) {
            // Find and supersede existing active override if present
            $existing = VendorSubscription::query()
                ->where('vendor_profile_id', $vendorProfileId)
                ->where('is_admin_override', true)
                ->whereNotIn('status', [SubscriptionStatus::Cancelled->value, SubscriptionStatus::Expired->value, SubscriptionStatus::Superseded->value])
                ->first();

            if ($existing) {
                $beforeState = ['status' => $existing->status->getValue()];
                $existing->update(['status' => SubscriptionStatus::Superseded->value, 'ended_at' => now()]);

                $this->auditWriter->write(
                    $existing,
                    SubscriptionEventType::Superseded,
                    beforeState: $beforeState,
                    afterState: ['status' => SubscriptionStatus::Superseded->value],
                    actorType: 'admin',
                    actorId: auth()->id(),
                );
            }

            // Create new override
            $newSub = VendorSubscription::create([
                'public_id' => Str::ulid(),
                'vendor_profile_id' => $vendorProfileId,
                'subscription_plan_id' => $plan->id,
                'is_admin_override' => true,
                'status' => SubscriptionStatus::Active->value,
                'billing_cycle' => 'none',
                'current_period_start' => now(),
                'current_period_end' => $expiresAt ?? now()->addCentury(),
                'override_expires_at' => $expiresAt,
                'started_at' => now(),
            ]);

            // Write audit entry
            $this->auditWriter->write(
                $newSub,
                SubscriptionEventType::AdminOverrideApplied,
                beforeState: [],
                afterState: ['plan_code' => $plan->plan_code->value],
                reason: json_encode(['en' => $reasonEn, 'ar' => $reasonAr]),
                actorType: 'admin',
                actorId: auth()->id(),
            );

            // Fire domain event after commit
            DB::afterCommit(fn () => event(new AdminOverrideApplied(
                $newSub,
                $plan->plan_code->value,
                json_encode(['en' => $reasonEn, 'ar' => $reasonAr])
            )));

            return $newSub;
        });
    }
}
