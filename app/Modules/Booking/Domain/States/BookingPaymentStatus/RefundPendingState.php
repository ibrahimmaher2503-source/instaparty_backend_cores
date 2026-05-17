<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\States\BookingPaymentStatus;

final class RefundPendingState extends BookingPaymentState
{
    public static string $name = 'refund_pending';
}
