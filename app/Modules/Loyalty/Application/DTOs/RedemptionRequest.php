<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Application\DTOs;

final readonly class RedemptionRequest
{
    public function __construct(
        public int $customerId,
        public int $vendorProfileId,
        public int $programId,
        public int $ruleId,
        public int $bookingId,
        public int $pointsToRedeem,
        public int $discountMinor,
        public string $discountCurrency,
    ) {}
}
