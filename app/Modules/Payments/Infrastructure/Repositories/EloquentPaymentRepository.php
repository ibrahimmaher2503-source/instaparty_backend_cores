<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Repositories;

use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Models\Payment;
use Carbon\Carbon;

class EloquentPaymentRepository
{
    public function create(array $attrs): Payment
    {
        return Payment::create($attrs);
    }

    public function findByPublicId(string $ulid): ?Payment
    {
        return Payment::query()->where('public_id', $ulid)->first();
    }

    public function findByGatewayRef(string $gateway, string $ref): ?Payment
    {
        return Payment::query()->where('gateway', $gateway)->where('gateway_ref', $ref)->first();
    }

    public function markCaptured(Payment $payment, Carbon $at): void
    {
        $payment->update(['status' => PaymentStatus::Captured, 'captured_at' => $at]);
    }

    public function markFailed(Payment $payment, string $code, array $message): void
    {
        $payment->update(['status' => PaymentStatus::Failed, 'failure_code' => $code, 'failure_message' => $message]);
    }
}
