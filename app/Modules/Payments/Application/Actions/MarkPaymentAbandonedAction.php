<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Actions;

use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Events\PaymentAbandoned;
use App\Modules\Payments\Domain\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarkPaymentAbandonedAction
{
    public function execute(int $paymentId, int $adminUserId): Payment
    {
        return DB::transaction(function () use ($paymentId, $adminUserId): Payment {
            /** @var Payment $payment */
            $payment = Payment::query()->lockForUpdate()->findOrFail($paymentId);

            if ($payment->status !== PaymentStatus::Failed) {
                throw ValidationException::withMessages([
                    'payment' => 'Only failed payments can be marked as abandoned.',
                ]);
            }

            $payment->update(['status' => PaymentStatus::Abandoned]);

            DB::afterCommit(fn () => PaymentAbandoned::dispatch(
                $payment->id,
                $payment->booking_id,
                $adminUserId,
            ));

            return $payment->fresh();
        });
    }
}
