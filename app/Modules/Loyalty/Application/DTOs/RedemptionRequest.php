<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Application\DTOs;

final readonly class RedemptionRequest
{
    public function __construct(
        public int $userId,
        public int $vendorProfileId,
        public int $programId,
        public int $bookingId,
        public int $pointsRedeemed,
        public int $amountMinor,
        public string $amountCurrency,
    ) {}
}
