<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Application\Actions;

use App\Modules\Loyalty\Domain\Contracts\BookingDiscountWriter;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyLedgerRepository;
use App\Modules\Loyalty\Domain\Enums\LedgerDirection;
use App\Modules\Loyalty\Domain\Events\LoyaltyRedemptionReversed;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;
use App\Modules\Loyalty\Domain\Models\LoyaltyRedemption;
use App\Modules\Loyalty\Domain\Services\BalanceCalculator;
use Illuminate\Support\Facades\DB;

class ReverseRedemptionAction
{
    public function __construct(
        private readonly LoyaltyLedgerRepository $ledger,
        private readonly BookingDiscountWriter $discountWriter,
        private readonly BalanceCalculator $balanceCalc,
    ) {}

    public function execute(LoyaltyRedemption $redemption, int $refundMinor): LoyaltyLedgerEntry
    {
        // Idempotent guard.
        $existing = LoyaltyLedgerEntry::query()
            ->where('direction', LedgerDirection::Adjust->value)
            ->where('reference_type', 'loyalty_redemption_reversal')
            ->where('reference_id', (int) $redemption->id)
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $originalAmount = max(1, (int) $redemption->amount_minor);
        $pointsToRestore = (int) floor(
            (int) $redemption->points_redeemed * $refundMinor / $originalAmount
        );
        $pointsToRestore = max(0, $pointsToRestore);

        $userId = (int) $redemption->user_id;
        $vendorProfileId = (int) $redemption->vendor_profile_id;
        $prevBalance = $this->balanceCalc->availableFor($userId, $vendorProfileId);

        return DB::transaction(function () use (
            $redemption, $refundMinor, $pointsToRestore, $prevBalance, $userId, $vendorProfileId
        ) {
            $entry = $this->ledger->append(
                userId: $userId,
                vendorProfileId: $vendorProfileId,
                programId: (int) $redemption->loyalty_program_id,
                direction: LedgerDirection::Adjust,
                points: $pointsToRestore,
                balanceAfter: $prevBalance + $pointsToRestore,
                referenceType: 'loyalty_redemption_reversal',
                referenceId: (int) $redemption->id,
                reason: [
                    'en' => 'Loyalty points restored due to refund on booking #'.$redemption->booking_id,
                    'ar' => 'استرداد نقاط الولاء بسبب استرجاع الحجز #'.$redemption->booking_id,
                ],
                expiresAt: null,
            );

            // Full refund => fully remove the loyalty discount line on the booking.
            if ($refundMinor >= (int) $redemption->amount_minor) {
                $this->discountWriter->removeLoyaltyDiscount(
                    (int) $redemption->booking_id,
                    (string) $redemption->public_id,
                );
            }

            DB::afterCommit(fn () => event(new LoyaltyRedemptionReversed($redemption)));

            return $entry;
        });
    }
}
