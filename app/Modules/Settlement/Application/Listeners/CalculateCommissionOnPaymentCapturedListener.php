<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Listeners;

use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Events\PaymentCaptured;
use App\Modules\Settlement\Application\Actions\CalculateCommissionAction;
use App\Modules\Settlement\Domain\Contracts\SettlementBookingReader;
use App\Modules\Settlement\Domain\Contracts\SettlementPaymentReader;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class CalculateCommissionOnPaymentCapturedListener implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(
        private SettlementPaymentReader $paymentReader,
        private SettlementBookingReader $bookingReader,
        private CalculateCommissionAction $calculateCommission,
    ) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(PaymentCaptured $event): void
    {
        $payment = $this->paymentReader->findById($event->paymentId);

        if ($payment === null) {
            Log::error('Settlement: PaymentCaptured received but payment not found', [
                'payment_id' => $event->paymentId,
            ]);

            return;
        }

        if ($payment->status !== PaymentStatus::Captured) {
            Log::error('Settlement: PaymentCaptured received but payment status is not captured', [
                'payment_id' => $event->paymentId,
                'status' => $payment->status->value,
            ]);

            return;
        }

        $items = $this->bookingReader->itemsForPayment($payment->bookingId);

        foreach ($items as $item) {
            try {
                $this->calculateCommission->execute($item, $payment);
            } catch (QueryException $e) {
                // Duplicate commission for this booking_item — idempotent, skip
                if ($e->getCode() === '23000') {
                    Log::info('Settlement: commission already calculated for booking item, skipping', [
                        'booking_item_id' => $item->id,
                        'payment_id' => $event->paymentId,
                    ]);

                    continue;
                }

                throw $e;
            }
        }
    }
}
