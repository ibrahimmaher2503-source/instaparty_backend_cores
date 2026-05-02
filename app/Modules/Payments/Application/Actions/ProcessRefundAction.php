<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Actions;

use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Events\RefundCompleted;
use App\Modules\Payments\Domain\Events\RefundFailed;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Payments\Infrastructure\Repositories\EloquentRefundRepository;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;

class ProcessRefundAction
{
    public function __construct(private readonly PaymentGateway $gateway, private readonly EloquentRefundRepository $refunds) {}

    public function execute(int $refundId): void
    {
        DB::transaction(function () use ($refundId): void {
            $refund = Refund::query()->lockForUpdate()->findOrFail($refundId);
            $payment = Payment::query()->findOrFail($refund->payment_id);
            $this->refunds->markProcessing($refund);

            $result = $this->gateway->refund($payment, Money::ofMinor($refund->amount_minor, $refund->amount_currency));
            if ($result->success) {
                $this->refunds->markCompleted($refund, (string) $result->gatewayRef, now());
                $payment->update(['status' => PaymentStatus::Refunded]);
                DB::afterCommit(fn () => event(new RefundCompleted($refund->id, $payment->id, $payment->booking_id, $refund->amount_minor, $refund->amount_currency, $refund->reason_code->value)));

                return;
            }

            $this->refunds->markFailed($refund, (string) $result->failureMessage);
            DB::afterCommit(fn () => event(new RefundFailed($refund->id, $payment->id, ['en' => (string) $result->failureMessage, 'ar' => (string) $result->failureMessage])));
        });
    }
}
