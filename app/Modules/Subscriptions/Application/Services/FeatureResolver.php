<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\Services;

use App\Modules\Subscriptions\Application\DTOs\PlanFeaturesDto;
use App\Modules\Subscriptions\Domain\Models\PlanFeature;
use Illuminate\Support\Facades\Cache;

class FeatureResolver
{
    private array $requestCache = [];

    private const CACHE_TTL_SECONDS = 3600;

    private const CACHE_KEY_PREFIX = 'subscription_plan_features:';

    public function resolveForPlan(int $planId): PlanFeaturesDto
    {
        if (isset($this->requestCache[$planId])) {
            return $this->requestCache[$planId];
        }

        $dto = Cache::remember(
            self::CACHE_KEY_PREFIX.$planId,
            self::CACHE_TTL_SECONDS,
            fn () => $this->loadFeaturesFromDb($planId),
        );

        $this->requestCache[$planId] = $dto;

        return $dto;
    }

    public function forgetForPlan(int $planId): void
    {
        Cache::forget(self::CACHE_KEY_PREFIX.$planId);
        unset($this->requestCache[$planId]);
    }

    private function loadFeaturesFromDb(int $planId): PlanFeaturesDto
    {
        $features = PlanFeature::where('subscription_plan_id', $planId)
            ->get()
            ->keyBy('feature_key');

        return new PlanFeaturesDto(
            maxActiveServices: (int) ($features->get('max_active_services')?->value_int ?? 5),
            featuredCap: (int) ($features->get('featured_cap')?->value_int ?? 0),
            canFeature: (bool) ($features->get('can_feature')?->value_bool ?? false),
            canImportExcel: (bool) ($features->get('can_import_excel')?->value_bool ?? false),
            commissionDiscountBps: (int) ($features->get('commission_discount_bps')?->value_int ?? 0),
        );
    }
}
