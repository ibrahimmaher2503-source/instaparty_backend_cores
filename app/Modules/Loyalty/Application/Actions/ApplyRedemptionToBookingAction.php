<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Application\Actions;

use App\Modules\Loyalty\Application\DTOs\RedemptionRequest;
use App\Modules\Loyalty\Domain\Contracts\BookingDiscountWriter;
use App\Modules\Loyalty\Domain\Contracts\BookingDraftReader;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyLedgerRepository;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyProgramRepository;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyRedemptionRepository;
use App\Modules\Loyalty\Domain\Enums\LedgerDirection;
use App\Modules\Loyalty\Domain\Events\LoyaltyRedemptionApplied;
use App\Modules\Loyalty\Domain\Models\LoyaltyRedemption;
use App\Modules\Loyalty\Domain\Services\BalanceCalculator;
use App\Modules\Loyalty\Domain\Services\DiscountCalculator;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ApplyRedemptionToBookingAction
{
    public function __construct(
        private readonly BookingDraftReader $draftReader,
        private readonly BookingDiscountWriter $discountWriter,
        private readonly LoyaltyProgramRepository $programs,
        private readonly LoyaltyRedemptionRepository $redemptions,
        private readonly LoyaltyLedgerRepository $ledger,
        private readonly BalanceCalculator $balanceCalc,
        private readonly DiscountCalculator $discountCalc,
    ) {}

    public function execute(int $userId, string $bookingPublicId, int $pointsToRedeem): LoyaltyRedemption
    {
        $draft = $this->draftReader->draftForPublicId($bookingPublicId);

        if ($draft->customerId !== $userId) {
            throw new AuthorizationException('loyalty.cross_customer_forbidden');
        }

        $program = $this->programs->findByVendor($draft->vendorProfileId);
        if ($program === null || ! $program->is_active) {
            throw new DomainException('loyalty.program_inactive');
        }

        if ($pointsToRedeem < (int) $program->min_points_to_redeem) {
            throw new DomainException('loyalty.below_min_threshold');
        }

        $available = $this->balanceCalc->availableFor($userId, $draft->vendorProfileId);
        if ($pointsToRedeem > $available) {
            throw new DomainException('loyalty.insufficient_balance');
        }

        $discountMinor = $this->discountCalc->discountMinorFor($program, $pointsToRedeem);
        if ($discountMinor <= 0) {
            throw new DomainException('loyalty.discount_zero');
        }

        // Enforce max_redeem_pct cap (whole-percent, 0–100).
        $maxAllowed = (int) floor($draft->subtotalMinor * (int) $program->max_redeem_pct / 100);
        if ($discountMinor > $maxAllowed) {
            throw new DomainException('loyalty.exceeds_max_redeem_pct');
        }

        return DB::transaction(function () use (
            $userId, $draft, $program, $pointsToRedeem, $discountMinor, $available
        ) {
            $redemption = $this->redemptions->create(new RedemptionRequest(
                userId: $userId,
                vendorProfileId: $draft->vendorProfileId,
                programId: (int) $program->id,
                bookingId: $draft->bookingId,
                pointsRedeemed: $pointsToRedeem,
                amountMinor: $discountMinor,
                amountCurrency: (string) $program->points_value_currency,
            ));

            $this->ledger->append(
                userId: $userId,
                vendorProfileId: $draft->vendorProfileId,
                programId: (int) $program->id,
                direction: LedgerDirection::Redeem,
                points: $pointsToRedeem,
                balanceAfter: $available - $pointsToRedeem,
                referenceType: 'loyalty_redemption',
                referenceId: (int) $redemption->id,
                reason: [
                    'en' => 'Points redeemed on booking #'.$draft->bookingId,
                    'ar' => 'استبدال نقاط على الحجز #'.$draft->bookingId,
                ],
                expiresAt: null,
            );

            $this->discountWriter->applyLoyaltyDiscount(
                $draft->bookingId,
                $discountMinor,
                (string) $program->points_value_currency,
                (string) $redemption->public_id,
            );

            DB::afterCommit(fn () => event(new LoyaltyRedemptionApplied($redemption)));

            return $redemption;
        });
    }
}
