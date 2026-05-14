<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\Services;

use App\Modules\Loyalty\Domain\Contracts\PointsBalanceReader;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;

class BalanceCalculator implements PointsBalanceReader
{
    /**
     * Available balance = balance_after of the most recent ledger row for (user, vendor).
     * Each append-only write recomputes and snapshots balance_after, so we never sum.
     */
    public function availableFor(int $customerId, int $vendorProfileId): int
    {
        $latest = LoyaltyLedgerEntry::query()
            ->where('user_id', $customerId)
            ->where('vendor_profile_id', $vendorProfileId)
            ->orderByDesc('id')
            ->first(['balance_after']);

        if ($latest === null) {
            return 0;
        }

        return max(0, (int) $latest->balance_after);
    }
}
