<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Events;

use App\Modules\Booking\Domain\Models\Booking;

final class BookingConfirmed
{
    public function __construct(
        public readonly Booking $booking,
    ) {}
}
