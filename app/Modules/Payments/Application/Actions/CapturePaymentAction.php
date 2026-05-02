<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Actions;

use App\Modules\Payments\Domain\Events\PaymentCaptured;
use App\Modules\Payments\Infrastructure\Repositories\EloquentPaymentRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CapturePaymentAction
{
    public function __construct(private readonly EloquentPaymentRepository $payments) {}

    public function execute(int $paymentId, ?Carbon $capturedAt = null): void
    {
        DB::transaction(function () use ($paymentId, $capturedAt): void {
            $payment = \App\Modules\Payments\Domain\Models\Payment::query()->lockForUpdate()->findOrFail($paymentId);
            $at = $capturedAt ?? now();
            $this->payments->markCaptured($payment, $at);

            DB::afterCommit(fn () => event(new PaymentCaptured($payment->id, $payment->booking_id, $payment->amount_minor, $payment->amount_currency, Carbon::instance($at))));
        });
    }
}
