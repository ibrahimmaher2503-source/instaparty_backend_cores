<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Application\Actions;

use App\Modules\Loyalty\Application\DTOs\RedemptionRequest;
use App\Modules\Loyalty\Domain\Contracts\BookingDiscountWriter;
use App\Modules\Loyalty\Domain\Contracts\BookingDraftReader;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyProgramRepository;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyRedemptionRepository;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyRuleRepository;
use App\Modules\Loyalty\Domain\Models\LoyaltyRedemption;
use App\Modules\Loyalty\Domain\Services\BalanceCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplyRedemptionToBookingAction
{
    public function __construct(
        private readonly BookingDraftReader $draftReader,
        private readonly BookingDiscountWriter $discountWriter,
        private readonly LoyaltyProgramRepository $programs,
        private readonly LoyaltyRuleRepository $rules,
        private readonly LoyaltyRedemptionRepository $redemptions,
        private readonly BalanceCalculator $balance,
    ) {}

    public function execute(int $customerId, string $bookingPublicId, int $pointsToRedeem): LoyaltyRedemption
    {
        return DB::transaction(function () use ($customerId, $bookingPublicId, $pointsToRedeem) {
            $booking = $this->draftReader->draftForPublicId($bookingPublicId);

            // Enforce per-vendor scoping
            if ($booking->customerId !== $customerId) {
                throw ValidationException::withMessages([
                    'booking' => [__('loyalty::loyalty.errors.cross_vendor_forbidden')],
                ]);
            }

            $program = $this->programs->findByVendor($booking->vendorProfileId);
            if ($program === null || ! $program->status->allowsRedemption()) {
                throw ValidationException::withMessages([
                    'program' => [__('loyalty::loyalty.errors.no_active_program')],
                ]);
            }

            $rule = $this->rules->activeRuleFor($program->id);
            if ($rule === null) {
                throw ValidationException::withMessages([
                    'rule' => [__('loyalty::loyalty.errors.no_active_program')],
                ]);
            }

            // Enforce minimum threshold
            if ($pointsToRedeem < $rule->min_points_to_redeem) {
                throw ValidationException::withMessages([
                    'points' => [__('loyalty::loyalty.errors.below_min_threshold', ['min' => $rule->min_points_to_redeem])],
                ]);
            }

            // Check available balance
            $available = $this->balance->availableFor($customerId, $booking->vendorProfileId);
            if ($pointsToRedeem > $available) {
                throw ValidationException::withMessages([
                    'points' => [__('loyalty::loyalty.errors.insufficient_balance')],
                ]);
            }

            // Compute discount
            $discountMinor = $rule->computeDiscountMinor($pointsToRedeem);

            // Enforce max_redeem_pct_bps cap
            $maxDiscount = (int) floor($booking->subtotalMinor * $rule->max_redeem_pct_bps / 10000);
            if ($discountMinor > $maxDiscount) {
                $maxPct = $rule->max_redeem_pct_bps / 100;
                throw ValidationException::withMessages([
                    'points' => [__('loyalty::loyalty.errors.exceeds_max_pct', ['pct' => $maxPct])],
                ]);
            }

            // Cap at subtotal
            $discountMinor = min($discountMinor, $booking->subtotalMinor);

            $redemption = $this->redemptions->create(new RedemptionRequest(
                customerId: $customerId,
                vendorProfileId: $booking->vendorProfileId,
                programId: $program->id,
                ruleId: $rule->id,
                bookingId: $booking->bookingId,
                pointsToRedeem: $pointsToRedeem,
                discountMinor: $discountMinor,
                discountCurrency: $booking->currency,
            ));

            $this->discountWriter->applyLoyaltyDiscount(
                $booking->bookingId,
                $discountMinor,
                $booking->currency,
                $redemption->public_id,
            );

            return $redemption;
        });
    }
}
