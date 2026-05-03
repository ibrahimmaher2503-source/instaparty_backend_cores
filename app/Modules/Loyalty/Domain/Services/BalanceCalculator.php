<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\Services;

use App\Modules\Loyalty\Domain\Contracts\LoyaltyLedgerRepository;
use App\Modules\Loyalty\Domain\Contracts\PointsBalanceReader;

class BalanceCalculator implements PointsBalanceReader
{
    public function __construct(
        private readonly LoyaltyLedgerRepository $ledger,
    ) {}

    public function availableFor(int $customerId, int $vendorProfileId): int
    {
        $total = $this->ledger->balanceFor($customerId, $vendorProfileId);
        $held  = $this->ledger->heldPointsFor($customerId, $vendorProfileId);
        return max(0, $total - $held);
    }
}
