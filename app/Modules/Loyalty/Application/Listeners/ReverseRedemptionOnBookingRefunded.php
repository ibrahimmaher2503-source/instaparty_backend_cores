<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Application\Listeners;

use App\Modules\Loyalty\Application\Actions\ReverseRedemptionAction;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyLedgerRepository;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyRedemptionRepository;
use App\Modules\Loyalty\Domain\Enums\LedgerDirection;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;
use App\Modules\Loyalty\Domain\Services\BalanceCalculator;
use App\Modules\Payments\Domain\Events\RefundCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class ReverseRedemptionOnBookingRefunded implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(
        private readonly LoyaltyRedemptionRepository $redemptions,
        private readonly ReverseRedemptionAction $reverse,
        private readonly LoyaltyLedgerRepository $ledger,
        private readonly BalanceCalculator $balanceCalc,
    ) {}

    public function handle(RefundCompleted $event): void
    {
        // 1) Reverse any active redemption on this booking.
        $redemption = $this->redemptions->findActiveForBooking($event->bookingId);
        if ($redemption !== null) {
            $this->reverse->execute($redemption, $event->amountMinor);
        }

        // 2) Proportionally reverse earned points for refunded booking items, if the
        // event payload identifies them. RefundCompleted today does not carry
        // booking_item_id / originalNetMinor scalars, so we only act when an
        // extended event shape provides them. Otherwise it's a no-op — the
        // redemption reversal above already handles the discount side.
        $bookingItemId = isset($event->bookingItemId) ? (int) $event->bookingItemId : null;
        $userId = isset($event->userId)
            ? (int) $event->userId
            : (isset($event->customerId) ? (int) $event->customerId : null);

        if ($bookingItemId === null || $userId === null) {
            return;
        }

        $earnEntry = LoyaltyLedgerEntry::query()
            ->where('direction', LedgerDirection::Earn->value)
            ->where('reference_type', 'booking_item')
            ->where('reference_id', $bookingItemId)
            ->first();

        if ($earnEntry === null) {
            return;
        }

        // Idempotency: skip if we already wrote an adjust for this earn row.
        $alreadyReversed = LoyaltyLedgerEntry::query()
            ->where('direction', LedgerDirection::Adjust->value)
            ->where('reference_type', 'earn_refund_reversal')
            ->where('reference_id', (int) $earnEntry->id)
            ->exists();
        if ($alreadyReversed) {
            return;
        }

        $originalNetMinor = isset($event->originalNetMinor) ? (int) $event->originalNetMinor : 0;
        $refundShare = $originalNetMinor > 0
            ? min(1.0, $event->amountMinor / $originalNetMinor)
            : 1.0;
        $pointsToReverse = (int) floor((int) $earnEntry->points * $refundShare);

        if ($pointsToReverse <= 0) {
            return;
        }

        DB::transaction(function () use ($earnEntry, $pointsToReverse) {
            $userId = (int) $earnEntry->user_id;
            $vendorProfileId = (int) $earnEntry->vendor_profile_id;
            $prevBalance = $this->balanceCalc->availableFor($userId, $vendorProfileId);
            $delta = min($pointsToReverse, $prevBalance);

            $this->ledger->append(
                userId: $userId,
                vendorProfileId: $vendorProfileId,
                programId: (int) $earnEntry->loyalty_program_id,
                direction: LedgerDirection::Adjust,
                points: $delta,
                balanceAfter: $prevBalance - $delta,
                referenceType: 'earn_refund_reversal',
                referenceId: (int) $earnEntry->id,
                reason: [
                    'en' => 'Earned points reversed proportionally to refund',
                    'ar' => 'تم خصم النقاط المكتسبة بالتناسب مع الاسترداد',
                ],
                expiresAt: null,
            );
        });
    }
}
