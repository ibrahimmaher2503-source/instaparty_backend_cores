<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exceptions;

use RuntimeException;

class ResponseDeadlineExpiredException extends RuntimeException
{
    public function __construct(
        public readonly int $bookingVendorId,
    ) {
        parent::__construct(
            __('booking.errors.response_deadline_expired'),
            409,
        );
    }
}
