<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Actions;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Payments\Application\DTOs\InitiateRefundDto;
use App\Modules\Payments\Application\Services\RefundPolicyService;
use App\Modules\Payments\Domain\Contracts\PaymentsBookingReader;
use App\Modules\Payments\Domain\Exceptions\PartialRefundUnsupportedException;
use App\Modules\Payments\Domain\Exceptions\RefundPolicyViolationException;
use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Payments\Infrastructure\Repositories\EloquentPaymentRepository;
use App\Modules\Payments\Infrastructure\Repositories\EloquentRefundRepository;
use Illuminate\Support\Facades\DB;

class InitiateRefundAction
{
    public function __construct(
        private readonly EloquentPaymentRepository $payments,
        private readonly EloquentRefundRepository $refunds,
        private readonly RefundPolicyService $policyService,
        private readonly PaymentsBookingReader $bookingReader,
        private readonly ProcessRefundAction $processRefund,
    ) {}

    public function execute(InitiateRefundDto $dto): Refund
    {
        $payment = $this->payments->findById($dto->paymentId)
            ?? abort(404, 'Payment not found');

        if ($dto->requestedAmountMinor !== null && $dto->requestedAmountMinor !== (int) $payment->amount_minor) {
            throw new PartialRefundUnsupportedException;
        }

        $items = $this->bookingReader->itemsFor($dto->bookingId);
        foreach ($items as $item) {
            $policy = $this->policyService->policyFor(
                ProductType::from($item->productType->value),
                $item->itemStatus,
                $item->eventStartsAt,
                $item->serviceId,
            );

            if (! $policy->allowed) {
                throw new RefundPolicyViolationException($policy->reasonCode, $policy->reasonMessageKey);
            }
        }

        return DB::transaction(function () use ($dto, $payment): Refund {
            $refund = $this->refunds->create($dto, (int) $payment->amount_minor, (string) $payment->amount_currency);
            $this->processRefund->execute($refund->id);

            return $refund->fresh() ?? $refund;
        });
    }
}
