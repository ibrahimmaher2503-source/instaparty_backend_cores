<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Application\Actions;

use App\Modules\Loyalty\Domain\Contracts\BookingDiscountWriter;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyLedgerRepository;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyRedemptionRepository;
use App\Modules\Loyalty\Domain\Enums\LedgerEntryType;
use App\Modules\Loyalty\Domain\Events\LoyaltyRedemptionApplied;
use App\Modules\Loyalty\Domain\Events\LoyaltyRedemptionReversed;
use App\Modules\Loyalty\Domain\Events\LoyaltyRedemptionVoided;
use App\Modules\Loyalty\Domain\Models\LoyaltyRedemption;
use Illuminate\Support\Facades\DB;

class FinalizeRedemptionAction
{
    public function __construct(
        private readonly LoyaltyRedemptionRepository $redemptions,
        private readonly LoyaltyLedgerRepository $ledger,
        private readonly BookingDiscountWriter $discountWriter,
    ) {}

    public function applyPending(LoyaltyRedemption $redemption): LoyaltyRedemption
    {
        if ((string) $redemption->status === 'applied') {
            return $redemption;
        }

        return DB::transaction(function () use ($redemption) {
            $redeemEntry = $this->ledger->append(
                type: LedgerEntryType::Redeem,
                customerId: $redemption->customer_id,
                vendorProfileId: $redemption->vendor_profile_id,
                programId: $redemption->loyalty_program_id,
                points: -$redemption->points_held,
                reason: ['en' => __('loyalty::loyalty.reason.redeem', [], 'en'), 'ar' => __('loyalty::loyalty.reason.redeem', [], 'ar')],
                bookingId: $redemption->booking_id,
                redemptionId: $redemption->id,
            );

            $updated = $this->redemptions->transitionTo($redemption, 'applied');

            DB::afterCommit(fn () => event(new LoyaltyRedemptionApplied($updated)));

            return $updated;
        });
    }

    public function voidPending(LoyaltyRedemption $redemption): LoyaltyRedemption
    {
        if ((string) $redemption->status === 'voided') {
            return $redemption;
        }

        return DB::transaction(function () use ($redemption) {
            $this->discountWriter->removeLoyaltyDiscount($redemption->booking_id, $redemption->public_id);
            $updated = $this->redemptions->transitionTo($redemption, 'voided');

            DB::afterCommit(fn () => event(new LoyaltyRedemptionVoided($updated)));

            return $updated;
        });
    }

    public function reverseApplied(LoyaltyRedemption $redemption, int $refundMinor): LoyaltyRedemption
    {
        if ((string) $redemption->status === 'reversed') {
            return $redemption;
        }

        return DB::transaction(function () use ($redemption, $refundMinor) {
            // Find original debit entry
            $originalDebit = $redemption->ledgerEntries()
                ->where('entry_type', LedgerEntryType::Redeem->value)
                ->first();

            // Compute proportional reversal using floor (ADR-0012 §11 decision)
            $pointsToRestore = $originalDebit
                ? (int) floor(abs($originalDebit->points) * ($refundMinor / max(1, $redemption->discount_minor)))
                : 0;

            if ($pointsToRestore > 0) {
                $this->ledger->append(
                    type: LedgerEntryType::Reversal,
                    customerId: $redemption->customer_id,
                    vendorProfileId: $redemption->vendor_profile_id,
                    programId: $redemption->loyalty_program_id,
                    points: $pointsToRestore,
                    reason: ['en' => __('loyalty::loyalty.reason.reversal', [], 'en'), 'ar' => __('loyalty::loyalty.reason.reversal', [], 'ar')],
                    bookingId: $redemption->booking_id,
                    redemptionId: $redemption->id,
                    reversedFromLedgerId: $originalDebit?->id,
                );
            }

            $updated = $this->redemptions->transitionTo($redemption, 'reversed');

            DB::afterCommit(fn () => event(new LoyaltyRedemptionReversed($updated)));

            return $updated;
        });
    }
}
