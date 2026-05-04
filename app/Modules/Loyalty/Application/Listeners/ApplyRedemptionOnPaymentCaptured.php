<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Application\Listeners;

use App\Modules\Loyalty\Application\Actions\FinalizeRedemptionAction;
use App\Modules\Loyalty\Domain\Contracts\LoyaltyRedemptionRepository;
use Illuminate\Contracts\Queue\ShouldQueue;

class ApplyRedemptionOnPaymentCaptured implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(
        private readonly LoyaltyRedemptionRepository $redemptions,
        private readonly FinalizeRedemptionAction $finalize,
    ) {}

    public function handle(object $event): void
    {
        if (! isset($event->bookingId)) {
            return;
        }

        $redemption = $this->redemptions->findActiveForBooking($event->bookingId);
        if ($redemption === null || (string) $redemption->status !== 'pending') {
            return;
        }

        $this->finalize->applyPending($redemption);
    }
}
