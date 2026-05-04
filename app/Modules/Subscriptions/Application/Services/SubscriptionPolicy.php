<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\Services;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Subscriptions\Application\DTOs\PolicyDecisionDto;
use App\Modules\Subscriptions\Domain\Contracts\CommissionTierLookup;
use App\Modules\Subscriptions\Domain\Contracts\SubscriptionPolicyContract;
use App\Modules\Subscriptions\Domain\Contracts\SubscriptionRepository;
use App\Modules\Subscriptions\Domain\Enums\PlanCode;
use App\Modules\Subscriptions\Domain\Models\VendorSubscription;

class SubscriptionPolicy implements SubscriptionPolicyContract, CommissionTierLookup
{
    public function __construct(
        private readonly SubscriptionRepository $repository,
        private readonly FeatureResolver $featureResolver,
    ) {}

    public function canCreateService(int $vendorProfileId, ProductType $type): PolicyDecisionDto
    {
        $sub = $this->effectiveSubscription($vendorProfileId);
        if ($sub === null) {
            return PolicyDecisionDto::allow();
        }

        $features = $this->featureResolver->resolveForPlan($sub->subscription_plan_id);
        $planCode = PlanCode::from($sub->plan->plan_code->value);

        $max = $features->maxActiveServices;

        if ($max < 0) {
            return PolicyDecisionDto::allow();
        }

        // Count active services for this vendor (cross-module via raw count — avoids model import)
        $activeCount = \DB::table('services')
            ->where('vendor_profile_id', $vendorProfileId)
            ->whereIn('status', ['published', 'draft'])
            ->whereNull('deleted_at')
            ->count();

        if ($activeCount < $max) {
            return PolicyDecisionDto::allow();
        }

        $unblocking = $planCode->firstUpgrade();

        return PolicyDecisionDto::deny(
            featureKey: 'max_active_services',
            currentCount: $activeCount,
            limit: $max,
            unblockingPlanCode: $unblocking?->value ?? 'premium',
            currentPlanCode: $planCode->value,
        );
    }

    public function canFeature(int $vendorProfileId): PolicyDecisionDto
    {
        $sub = $this->effectiveSubscription($vendorProfileId);
        if ($sub === null) {
            return PolicyDecisionDto::deny('can_feature', 0, 0, 'silver', 'free');
        }

        $features = $this->featureResolver->resolveForPlan($sub->subscription_plan_id);
        $planCode = PlanCode::from($sub->plan->plan_code->value);

        if (! $features->canFeature) {
            $unblocking = $planCode->firstUpgrade();
            return PolicyDecisionDto::deny('can_feature', 0, 0, $unblocking?->value ?? 'silver', $planCode->value);
        }

        return PolicyDecisionDto::allow();
    }

    public function canImportExcel(int $vendorProfileId): PolicyDecisionDto
    {
        $sub = $this->effectiveSubscription($vendorProfileId);
        if ($sub === null) {
            return PolicyDecisionDto::deny('can_import_excel', 0, 0, 'gold', 'free');
        }

        $features = $this->featureResolver->resolveForPlan($sub->subscription_plan_id);
        $planCode = PlanCode::from($sub->plan->plan_code->value);

        if (! $features->canImportExcel) {
            $unblocking = $planCode->firstUpgrade();
            return PolicyDecisionDto::deny('can_import_excel', 0, 0, $unblocking?->value ?? 'gold', $planCode->value);
        }

        return PolicyDecisionDto::allow();
    }

    public function featuredCap(int $vendorProfileId): int
    {
        $sub = $this->effectiveSubscription($vendorProfileId);
        if ($sub === null) {
            return 0;
        }
        return $this->featureResolver->resolveForPlan($sub->subscription_plan_id)->featuredCap;
    }

    public function maxActiveServices(int $vendorProfileId): int
    {
        $sub = $this->effectiveSubscription($vendorProfileId);
        if ($sub === null) {
            return 5; // Free tier default
        }
        return $this->featureResolver->resolveForPlan($sub->subscription_plan_id)->maxActiveServices;
    }

    public function commissionDiscountBps(int $vendorProfileId): int
    {
        $sub = $this->effectiveSubscription($vendorProfileId);
        if ($sub === null) {
            return 0;
        }
        return $this->featureResolver->resolveForPlan($sub->subscription_plan_id)->commissionDiscountBps;
    }

    public function currentPlanCode(int $vendorProfileId): string
    {
        $sub = $this->effectiveSubscription($vendorProfileId);
        return $sub?->plan?->plan_code->value ?? PlanCode::Free->value;
    }

    public function resolveTierBps(int $vendorProfileId): ?int
    {
        $bps = $this->commissionDiscountBps($vendorProfileId);
        return $bps > 0 ? $bps : null;
    }

    private function effectiveSubscription(int $vendorProfileId): ?VendorSubscription
    {
        return $this->repository->currentForVendor($vendorProfileId);
    }
}
