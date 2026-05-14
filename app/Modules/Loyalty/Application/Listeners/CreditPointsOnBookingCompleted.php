<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Application\Listeners;

use App\Modules\Loyalty\Application\Actions\CalculateLoyaltyPointsAction;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Credit loyalty points on BookingCompleted.
 *
 * The listener keeps a tolerant fallback for scalar event payloads (used by
 * older tests / outbox replays), but the primary path expects an event
 * carrying a Booking model.
 */
class CreditPointsOnBookingCompleted implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(
        private readonly CalculateLoyaltyPointsAction $action,
    ) {}

    public function handle(object $event): void
    {
        // Preferred shape: event carries a Booking model with items.
        if (isset($event->booking) && is_object($event->booking)) {
            $booking = $event->booking;
            $bookingId = (int) ($booking->id ?? 0);
            $userId = (int) ($booking->customer_id ?? 0);

            if ($bookingId === 0 || $userId === 0) {
                return;
            }

            // Iterate items if available; otherwise no-op (nothing to credit per-item).
            $items = method_exists($booking, 'items')
                ? $booking->items()->get(['id'])
                : (property_exists($booking, 'items') ? $booking->items : collect());

            foreach ($items as $item) {
                $bookingItemId = (int) ($item->id ?? 0);
                if ($bookingItemId === 0) {
                    continue;
                }
                $this->action->execute($userId, $bookingItemId, $bookingId);
            }

            return;
        }

        // Fallback: scalar event payload.
        if (! isset($event->bookingItemId, $event->bookingId)) {
            return;
        }
        $userId = (int) ($event->userId ?? $event->customerId ?? 0);
        if ($userId === 0) {
            return;
        }
        $this->action->execute($userId, (int) $event->bookingItemId, (int) $event->bookingId);
    }
}
