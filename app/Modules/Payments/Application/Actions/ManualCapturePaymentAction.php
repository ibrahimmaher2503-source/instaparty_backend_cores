<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Actions;

use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Events\PaymentCaptured;
use App\Modules\Payments\Domain\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManualCapturePaymentAction
{
    public function execute(int $paymentId, int $adminUserId, string $reason): Payment
    {
        return DB::transaction(function () use ($paymentId): Payment {
            /** @var Payment $payment */
            $payment = Payment::query()->lockForUpdate()->findOrFail($paymentId);

            if ($payment->status !== PaymentStatus::Authorized) {
                throw ValidationException::withMessages([
                    'payment' => 'Only authorized payments can be manually captured.',
                ]);
            }

            $capturedAt = now();

            $payment->update([
                'status' => PaymentStatus::Captured,
                'captured_at' => $capturedAt,
            ]);

            // Fires the SAME event as webhook-driven capture to ensure
            // Settlement commission listeners and Booking status listeners fire automatically
            DB::afterCommit(fn () => PaymentCaptured::dispatch(
                $payment->id,
                $payment->booking_id,
                $payment->amount_minor,
                $payment->amount_currency,
                Carbon::instance($capturedAt),
            ));

            return $payment->fresh();
        });
    }
}
