<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\Events;

use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;

final readonly class LoyaltyPointsEarned
{
    public function __construct(
        public LoyaltyLedgerEntry $entry,
    ) {}
}
