<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Application\Actions;

use App\Modules\Loyalty\Domain\Contracts\LoyaltyLedgerRepository;
use App\Modules\Loyalty\Domain\Enums\LedgerDirection;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;
use App\Modules\Loyalty\Domain\Services\BalanceCalculator;
use Illuminate\Support\Facades\DB;

class ExpireLoyaltyPointsAction
{
    public function __construct(
        private readonly LoyaltyLedgerRepository $ledger,
        private readonly BalanceCalculator $balanceCalc,
    ) {}

    /**
     * Scan ledger for direction=earn rows whose expires_at <= now() and have not
     * already been offset by a (direction=expire, reference_type=earn_expiry,
     * reference_id=earnRow.id) row. For each, append a compensating Expire ledger
     * row clamped to the user×vendor running balance.
     *
     * Returns the number of expire rows appended.
     */
    public function execute(): int
    {
        $now = now();

        $candidates = LoyaltyLedgerEntry::query()
            ->where('direction', LedgerDirection::Earn->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->orderBy('id')
            ->get();

        if ($candidates->isEmpty()) {
            return 0;
        }

        // Filter out already-expired earns (idempotent re-runs).
        $earnIds = $candidates->pluck('id')->all();
        $alreadyExpired = LoyaltyLedgerEntry::query()
            ->where('direction', LedgerDirection::Expire->value)
            ->where('reference_type', 'earn_expiry')
            ->whereIn('reference_id', $earnIds)
            ->pluck('reference_id')
            ->all();
        $alreadyExpiredSet = array_flip($alreadyExpired);

        $count = 0;

        foreach ($candidates as $earn) {
            if (isset($alreadyExpiredSet[(int) $earn->id])) {
                continue;
            }

            $userId = (int) $earn->user_id;
            $vendorProfileId = (int) $earn->vendor_profile_id;

            DB::transaction(function () use ($earn, $userId, $vendorProfileId) {
                $prevBalance = $this->balanceCalc->availableFor($userId, $vendorProfileId);
                // Cannot expire more than the user actually has left.
                $pointsToExpire = min((int) $earn->points, $prevBalance);
                if ($pointsToExpire <= 0) {
                    // Still record the offset to keep idempotency tight — append a 0-pt row.
                    $pointsToExpire = 0;
                }

                $this->ledger->append(
                    userId: $userId,
                    vendorProfileId: $vendorProfileId,
                    programId: (int) $earn->loyalty_program_id,
                    direction: LedgerDirection::Expire,
                    points: $pointsToExpire,
                    balanceAfter: $prevBalance - $pointsToExpire,
                    referenceType: 'earn_expiry',
                    referenceId: (int) $earn->id,
                    reason: [
                        'en' => 'Points expired',
                        'ar' => 'انتهت صلاحية النقاط',
                    ],
                    expiresAt: null,
                );
            });

            $count++;
        }

        return $count;
    }
}
