<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Application\Actions;

use App\Modules\Loyalty\Domain\Contracts\BookingDiscountWriter;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyLedgerRepository;
use App\Modules\Loyalty\Domain\Enums\LedgerDirection;
use App\Modules\Loyalty\Domain\Events\LoyaltyRedemptionVoided;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;
use App\Modules\Loyalty\Domain\Models\LoyaltyRedemption;
use App\Modules\Loyalty\Domain\Services\BalanceCalculator;
use Illuminate\Support\Facades\DB;

class VoidRedemptionAction
{
    public function __construct(
        private readonly LoyaltyLedgerRepository $ledger,
        private readonly BookingDiscountWriter $discountWriter,
        private readonly BalanceCalculator $balanceCalc,
    ) {}

    public function execute(LoyaltyRedemption $redemption): LoyaltyLedgerEntry
    {
        // Idempotent guard.
        $existing = LoyaltyLedgerEntry::query()
            ->where('direction', LedgerDirection::Adjust->value)
            ->where('reference_type', 'loyalty_redemption_void')
            ->where('reference_id', (int) $redemption->id)
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $userId = (int) $redemption->user_id;
        $vendorProfileId = (int) $redemption->vendor_profile_id;
        $pointsToRestore = (int) $redemption->points_redeemed;
        $prevBalance = $this->balanceCalc->availableFor($userId, $vendorProfileId);

        return DB::transaction(function () use (
            $redemption, $pointsToRestore, $prevBalance, $userId, $vendorProfileId
        ) {
            $entry = $this->ledger->append(
                userId: $userId,
                vendorProfileId: $vendorProfileId,
                programId: (int) $redemption->loyalty_program_id,
                direction: LedgerDirection::Adjust,
                points: $pointsToRestore,
                balanceAfter: $prevBalance + $pointsToRestore,
                referenceType: 'loyalty_redemption_void',
                referenceId: (int) $redemption->id,
                reason: [
                    'en' => 'Loyalty points restored — redemption voided on booking #'.$redemption->booking_id,
                    'ar' => 'إعادة نقاط الولاء — تم إلغاء استبدال الحجز #'.$redemption->booking_id,
                ],
                expiresAt: null,
            );

            $this->discountWriter->removeLoyaltyDiscount(
                (int) $redemption->booking_id,
                (string) $redemption->public_id,
            );

            DB::afterCommit(fn () => event(new LoyaltyRedemptionVoided($redemption)));

            return $entry;
        });
    }
}
