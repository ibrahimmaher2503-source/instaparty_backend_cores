<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Events;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;

final class BookingSubmittedToVendor
{
    public function __construct(
        public readonly BookingVendor $bookingVendor,
        public readonly Booking $booking,
    ) {}
}
