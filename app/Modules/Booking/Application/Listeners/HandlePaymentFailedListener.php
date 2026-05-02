<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Listeners;

use App\Modules\Payments\Domain\Events\PaymentFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class HandlePaymentFailedListener implements ShouldQueue
{
    public function handle(PaymentFailed $event): void
    {
        Log::warning('payment failed', ['payment_id' => $event->paymentId, 'booking_id' => $event->bookingId, 'failure_code' => $event->failureCode]);
    }
}
