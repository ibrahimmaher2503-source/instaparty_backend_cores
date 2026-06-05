<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Events;

use App\Modules\Booking\Domain\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Carries the Booking model. A QUEUED listener consumes this event
 * (Loyalty\VoidRedemptionOnBookingCancelled implements ShouldQueue), so
 * without SerializesModels Laravel would PHP-serialize the whole model graph
 * onto the queue — the ff6ce5e anti-pattern. SerializesModels stores only
 * class + PK and re-fetches a fresh model on the worker; every listener that
 * needs relations re-loads them (loadMissing). Sync listeners (afterCommit)
 * are unaffected and still receive the in-memory model.
 */
final class BookingCancelled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Booking $booking,
    ) {}
}
