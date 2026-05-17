<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exceptions;

use RuntimeException;

class BookingNotModifiableException extends RuntimeException
{
    public function __construct(
        public readonly int $bookingId,
        public readonly string $reasonKey,
    ) {
        parent::__construct(
            __('booking.errors.'.$reasonKey),
            422,
        );
    }
}
