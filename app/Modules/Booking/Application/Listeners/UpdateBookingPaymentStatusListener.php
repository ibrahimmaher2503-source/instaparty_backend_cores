<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Listeners;

use App\Modules\Payments\Domain\Events\PaymentCaptured;
use App\Modules\Payments\Domain\Events\RefundCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class UpdateBookingPaymentStatusListener implements ShouldQueue
{
    public function handle(PaymentCaptured $event): void
    {
        DB::table('bookings')->where('id', $event->bookingId)->update(['payment_status' => 'paid', 'updated_at' => now()]);
    }

    public function handleRefund(RefundCompleted $event): void
    {
        DB::table('bookings')->where('id', $event->bookingId)->update(['payment_status' => 'refunded', 'updated_at' => now()]);
    }
}
