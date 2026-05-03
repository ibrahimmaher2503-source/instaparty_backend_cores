<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Application\Listeners;

use App\Modules\Loyalty\Application\Actions\FinalizeRedemptionAction;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyRedemptionRepository;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyLedgerRepository;
use App\Modules\Loyalty\Domain\Enums\LedgerEntryType;
use App\Modules\Loyalty\Domain\Contracts\BookingItemNetAmountReader;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyProgramRepository;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyRuleRepository;
use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class ReverseRedemptionOnBookingRefunded implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(
        private readonly LoyaltyRedemptionRepository $redemptions,
        private readonly FinalizeRedemptionAction $finalize,
        private readonly LoyaltyLedgerRepository $ledger,
        private readonly LoyaltyProgramRepository $programs,
        private readonly LoyaltyRuleRepository $rules,
    ) {}

    public function handle(object $event): void
    {
        // Reverse applied redemption if present
        if (isset($event->bookingId)) {
            $redemption = $this->redemptions->findActiveForBooking($event->bookingId);
            if ($redemption && (string) $redemption->status === 'applied') {
                $refundMinor = $event->refundMinor ?? $redemption->discount_minor;
                $this->finalize->reverseApplied($redemption, $refundMinor);
            }
        }

        // Reverse earn credits for refunded booking_item
        if (isset($event->bookingItemId, $event->customerId)) {
            $earnEntry = LoyaltyLedgerEntry::where('booking_item_id', $event->bookingItemId)
                ->where('entry_type', LedgerEntryType::Earn->value)
                ->first();

            if ($earnEntry === null) {
                return;
            }

            // Idempotent: skip if reversal already exists for this earn entry
            $alreadyReversed = LoyaltyLedgerEntry::where('reversed_from_ledger_id', $earnEntry->id)
                ->where('entry_type', LedgerEntryType::Reversal->value)
                ->exists();

            if ($alreadyReversed) {
                return;
            }

            $originalPoints = abs($earnEntry->points);
            $refundMinor = $event->refundMinor ?? 0;
            $originalNetMinor = $event->originalNetMinor ?? 0;
            $refundShare = $originalNetMinor > 0 ? ($refundMinor / $originalNetMinor) : 1.0;
            $pointsToReverse = (int) floor($originalPoints * min(1.0, $refundShare));

            if ($pointsToReverse > 0) {
                DB::transaction(function () use ($earnEntry, $pointsToReverse) {
                    $this->ledger->append(
                        type: LedgerEntryType::Reversal,
                        customerId: $earnEntry->customer_id,
                        vendorProfileId: $earnEntry->vendor_profile_id,
                        programId: $earnEntry->loyalty_program_id,
                        points: -$pointsToReverse,
                        reason: ['en' => __('loyalty::loyalty.reason.reversal', [], 'en'), 'ar' => __('loyalty::loyalty.reason.reversal', [], 'ar')],
                        bookingId: $earnEntry->booking_id,
                        bookingItemId: $earnEntry->booking_item_id,
                        reversedFromLedgerId: $earnEntry->id,
                    );
                });
            }
        }
    }
}
