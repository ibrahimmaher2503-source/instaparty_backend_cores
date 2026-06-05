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
        // amount_paid_minor MUST be populated here: the cancellation refund
        // pipeline (PreviewBookingCancellationAction / OnBookingCancelled-
        // InitiateRefundListener) gates entirely on this column. Increment so
        // multiple captures on one booking accumulate correctly.
        DB::table('bookings')->where('id', $event->bookingId)->update([
            'payment_status' => 'paid',
            'amount_paid_minor' => DB::raw('amount_paid_minor + '.(int) $event->amountMinor),
            'amount_paid_currency' => $event->amountCurrency,
            'updated_at' => now(),
        ]);
    }

    public function handleRefund(RefundCompleted $event): void
    {
        // CASE (not GREATEST) — portable across MySQL (prod) and SQLite (tests).
        $delta = (int) $event->amountMinor;
        DB::table('bookings')->where('id', $event->bookingId)->update([
            'payment_status' => 'refunded',
            'amount_paid_minor' => DB::raw("CASE WHEN amount_paid_minor - {$delta} < 0 THEN 0 ELSE amount_paid_minor - {$delta} END"),
            'updated_at' => now(),
        ]);
    }
}
