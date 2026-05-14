<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Application\Listeners;

use App\Modules\Booking\Domain\Events\BookingCancelled;
use App\Modules\Loyalty\Application\Actions\VoidRedemptionAction;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyRedemptionRepository;
use Illuminate\Contracts\Queue\ShouldQueue;

class VoidRedemptionOnBookingCancelled implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(
        private readonly LoyaltyRedemptionRepository $redemptions,
        private readonly VoidRedemptionAction $void,
    ) {}

    public function handle(BookingCancelled $event): void
    {
        $bookingId = (int) ($event->booking->id ?? 0);
        if ($bookingId === 0) {
            return;
        }

        $redemption = $this->redemptions->findActiveForBooking($bookingId);
        if ($redemption === null) {
            return;
        }

        $this->void->execute($redemption);
    }
}
