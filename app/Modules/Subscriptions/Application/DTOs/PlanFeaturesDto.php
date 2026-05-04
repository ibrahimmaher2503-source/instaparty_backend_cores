<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\DTOs;

final class PlanFeaturesDto
{
    public function __construct(
        public readonly int $maxActiveServices,
        public readonly int $featuredCap,
        public readonly bool $canFeature,
        public readonly bool $canImportExcel,
        public readonly int $commissionDiscountBps,
    ) {}
}
