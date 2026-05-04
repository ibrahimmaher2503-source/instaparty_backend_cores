<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\DTOs;

use App\Modules\Subscriptions\Domain\Enums\BillingCycle;
use App\Modules\Subscriptions\Domain\Enums\PlanCode;

final class SubscribeRequestDto
{
    public function __construct(
        public readonly int $vendorProfileId,
        public readonly PlanCode $planCode,
        public readonly BillingCycle $billingCycle,
        public readonly bool $savePaymentToken,
        public readonly string $idempotencyKey,
    ) {}
}
