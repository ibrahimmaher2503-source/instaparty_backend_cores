<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Events;

use App\Modules\Booking\Domain\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a booking transitions to its terminal "completed" state — i.e.,
 * every booking item's fulfillment_status has reached Completed.
 *
 * TODO: Fire this from the booking state machine when fulfillment_status
 * reaches Completed for all items. Wiring the firing into the state machine
 * is out of scope of the Loyalty module; this class is created so that the
 * Loyalty module can listen for it without referencing a missing class.
 */
final class BookingCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Booking $booking,
    ) {}
}
