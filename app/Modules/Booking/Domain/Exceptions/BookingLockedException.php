<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exceptions;

use RuntimeException;

class BookingLockedException extends RuntimeException
{
    public function __construct(
        public readonly int $bookingId,
        public readonly string $resourceType,
    ) {
        parent::__construct(
            __('booking.errors.booking_locked'),
            423,
        );
    }
}
