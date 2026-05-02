<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Actions;

use App\Modules\Payments\Domain\Contracts\PaymentsBookingReader;
use App\Modules\Payments\Infrastructure\Repositories\EloquentPaymentRepository;
use Illuminate\Support\Facades\DB;

class ExpirePendingPaymentsAction
{
    public function __construct(private readonly PaymentsBookingReader $bookingReader, private readonly EloquentPaymentRepository $payments) {}

    public function execute(): void
    {
        DB::transaction(function (): void {
            $bookingIds = $this->bookingReader->staleHoldBookingIds();
            if ($bookingIds === []) {
                return;
            }

            $rows = \App\Modules\Payments\Domain\Models\Payment::query()->whereIn('booking_id', $bookingIds)->pending()->lockForUpdate()->get();
            foreach ($rows as $payment) {
                $this->payments->markFailed($payment, 'expired_payment_hold', ['en' => __('payments::failures.expired_payment_hold', [], 'en'), 'ar' => __('payments::failures.expired_payment_hold', [], 'ar')]);
            }
        });
    }
}
