<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Domain\Contracts;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Subscriptions\Application\DTOs\PolicyDecisionDto;

interface SubscriptionPolicyContract
{
    public function canCreateService(int $vendorProfileId, ProductType $type): PolicyDecisionDto;

    public function canFeature(int $vendorProfileId): PolicyDecisionDto;

    public function canImportExcel(int $vendorProfileId): PolicyDecisionDto;

    public function featuredCap(int $vendorProfileId): int;

    public function maxActiveServices(int $vendorProfileId): int;

    public function commissionDiscountBps(int $vendorProfileId): int;

    public function currentPlanCode(int $vendorProfileId): string;
}
