<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Application\Listeners;

use App\Modules\Loyalty\Application\Actions\CalculateLoyaltyPointsAction;
use Illuminate\Contracts\Queue\ShouldQueue;

class CreditPointsOnBookingCompleted implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(
        private readonly CalculateLoyaltyPointsAction $action,
    ) {}

    public function handle(object $event): void
    {
        // Expects event to carry: customer_id, booking_item_id, booking_id
        if (!isset($event->bookingItemId, $event->customerId, $event->bookingId)) {
            return;
        }

        $this->action->execute($event->customerId, $event->bookingItemId, $event->bookingId);
    }
}
