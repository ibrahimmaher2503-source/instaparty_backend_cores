<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Repositories;

use App\Modules\Payments\Application\DTOs\InitiateRefundDto;
use App\Modules\Payments\Domain\Enums\RefundStatus;
use App\Modules\Payments\Domain\Models\Refund;
use Carbon\Carbon;

class EloquentRefundRepository
{
    public function create(InitiateRefundDto $dto, int $amountMinor, string $amountCurrency): Refund
    {
        return Refund::create([
            'public_id' => (string) \Illuminate\Support\Str::ulid(),
            'payment_id' => $dto->paymentId,
            'booking_id' => $dto->bookingId,
            'amount_minor' => $amountMinor,
            'amount_currency' => $amountCurrency,
            'reason_code' => $dto->reasonCode,
            'reason_notes' => $dto->reasonNotes,
            'status' => RefundStatus::Pending,
            'initiated_by' => $dto->initiatedBy,
        ]);
    }

    public function markProcessing(Refund $refund): void
    {
        $refund->update(['status' => RefundStatus::Processing]);
    }

    public function markCompleted(Refund $refund, string $gatewayRef, Carbon $at): void
    {
        $refund->update(['status' => RefundStatus::Completed, 'gateway_ref' => $gatewayRef, 'processed_at' => $at]);
    }

    public function markFailed(Refund $refund, string $message): void
    {
        $refund->update(['status' => RefundStatus::Failed, 'reason_notes' => ['en' => $message, 'ar' => $message]]);
    }
}
