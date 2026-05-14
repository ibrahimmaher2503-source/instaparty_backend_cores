<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Application\Actions;

use App\Modules\Loyalty\Domain\Contracts\BookingItemNetAmountReader;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyLedgerRepository;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyProgramRepository;
use App\Modules\Loyalty\Domain\Enums\LedgerDirection;
use App\Modules\Loyalty\Domain\Events\LoyaltyPointsEarned;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;
use App\Modules\Loyalty\Domain\Services\BalanceCalculator;
use App\Modules\Loyalty\Domain\Services\PointsCalculator;
use App\Modules\Loyalty\Domain\Services\RuleResolver;
use Illuminate\Support\Facades\DB;

class CalculateLoyaltyPointsAction
{
    public function __construct(
        private readonly BookingItemNetAmountReader $netReader,
        private readonly LoyaltyProgramRepository $programs,
        private readonly LoyaltyLedgerRepository $ledger,
        private readonly PointsCalculator $pointsCalc,
        private readonly RuleResolver $ruleResolver,
        private readonly BalanceCalculator $balanceCalc,
    ) {}

    public function execute(int $userId, int $bookingItemId, int $bookingId): ?LoyaltyLedgerEntry
    {
        $vendorProfileId = $this->netReader->vendorProfileIdFor($bookingItemId);

        $program = $this->programs->findByVendor($vendorProfileId);
        if ($program === null || ! $program->is_active) {
            return null;
        }

        // Idempotent guard: only one earn per booking_item.
        if ($this->ledger->hasEarnForReference('booking_item', $bookingItemId)) {
            return null;
        }

        $netMinor = $this->netReader->netPaidMinorFor($bookingItemId);
        if ($netMinor <= 0) {
            return null;
        }

        $rule = $this->ruleResolver->resolveForBookingItem($program, $bookingItemId, $userId);

        $points = $this->pointsCalc->earnPointsFor($program, $netMinor, $rule);
        if ($points <= 0) {
            return null;
        }

        // Per-day cap: clamp to remaining daily allowance for (user, vendor).
        $capPerDay = (int) config('loyalty.max_points_per_day', 50000);
        $earnedToday = $this->ledger->pointsEarnedTodayFor($userId, $vendorProfileId);
        $remaining = $capPerDay - $earnedToday;
        if ($remaining <= 0) {
            return null;
        }
        $points = min($points, $remaining);
        if ($points <= 0) {
            return null;
        }

        $prevBalance = $this->balanceCalc->availableFor($userId, $vendorProfileId);
        $balanceAfter = $prevBalance + $points;

        $expiresAt = $program->points_expire_after_days
            ? now()->addDays((int) $program->points_expire_after_days)
            : null;

        return DB::transaction(function () use (
            $userId, $vendorProfileId, $program, $points, $balanceAfter,
            $bookingItemId, $expiresAt
        ) {
            $entry = $this->ledger->append(
                userId: $userId,
                vendorProfileId: $vendorProfileId,
                programId: (int) $program->id,
                direction: LedgerDirection::Earn,
                points: $points,
                balanceAfter: $balanceAfter,
                referenceType: 'booking_item',
                referenceId: $bookingItemId,
                reason: [
                    'en' => 'Points earned from booking item #'.$bookingItemId,
                    'ar' => 'نقاط مكتسبة من حجز #'.$bookingItemId,
                ],
                expiresAt: $expiresAt,
            );

            // TODO: write event_outbox row when Shared\Outbox helper lands.

            DB::afterCommit(fn () => event(new LoyaltyPointsEarned($entry)));

            return $entry;
        });
    }
}
