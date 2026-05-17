<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\States\BookingPaymentStatus;

final class PartiallyRefundedState extends BookingPaymentState
{
    public static string $name = 'partially_refunded';
}
