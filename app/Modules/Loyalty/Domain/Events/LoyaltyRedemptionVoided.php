<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\Events;

use App\Modules\Loyalty\Domain\Models\LoyaltyRedemption;

final readonly class LoyaltyRedemptionVoided
{
    public function __construct(
        public LoyaltyRedemption $redemption,
    ) {}
}
