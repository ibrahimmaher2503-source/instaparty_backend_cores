<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\Actions;

use App\Modules\Subscriptions\Application\Services\FeatureResolver;

class InvalidatePlanFeaturesCache
{
    public function __construct(
        private FeatureResolver $featureResolver,
    ) {}

    public function execute(int $planId): void
    {
        $this->featureResolver->forgetForPlan($planId);
    }
}
