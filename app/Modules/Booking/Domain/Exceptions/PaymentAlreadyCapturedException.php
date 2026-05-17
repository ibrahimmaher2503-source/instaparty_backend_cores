<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exceptions;

use RuntimeException;

class PaymentAlreadyCapturedException extends RuntimeException
{
    public function __construct(
        public readonly int $bookingId,
    ) {
        parent::__construct(
            __('booking.errors.payment_already_captured'),
            422,
        );
    }
}
