<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Actions;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Payments\Application\DTOs\InitiateRefundDto;
use App\Modules\Payments\Application\Services\RefundPolicyService;
use App\Modules\Payments\Domain\Contracts\PaymentsBookingReader;
use App\Modules\Payments\Infrastructure\Repositories\EloquentIdempotencyKeyRepository;
use App\Modules\Payments\Infrastructure\Repositories\EloquentRefundRepository;
use Illuminate\Support\Facades\DB;

class InitiateRefundAction
{
    public function __construct(
        private readonly EloquentIdempotencyKeyRepository $idempotency,
        private readonly EloquentRefundRepository $refunds,
        private readonly RefundPolicyService $policyService,
        private readonly PaymentsBookingReader $bookingReader,
        private readonly ProcessRefundAction $processRefund,
    ) {}

    public function execute(InitiateRefundDto $dto): array
    {
        $hash = hash('sha256', implode('|', [$dto->paymentId, $dto->bookingId, $dto->reasonCode->value, json_encode($dto->reasonNotes)]));
        $cached = $this->idempotency->lookup($dto->idempotencyKey, $dto->initiatedBy, $dto->route);
        if ($cached !== null) {
            if ($cached->request_hash !== $hash) {
                abort(409, 'Idempotency key conflict');
            }

            return $cached->response_body ?? [];
        }

        return DB::transaction(function () use ($dto, $hash): array {
            $payment = \App\Modules\Payments\Domain\Models\Payment::query()->findOrFail($dto->paymentId);
            $items = $this->bookingReader->itemsFor($dto->bookingId);

            foreach ($items as $item) {
                $policy = $this->policyService->policyFor(ProductType::from($item->productType->value), $item->itemStatus, $item->eventStartsAt, $item->serviceId);
                if (! $policy->allowed) {
                    abort(422, $policy->reasonCode);
                }
            }

            $refund = $this->refunds->create($dto, $payment->amount_minor, $payment->amount_currency);
            $this->processRefund->execute($refund->id);

            $payload = ['refund_public_id' => $refund->public_id, 'status' => $refund->status->value];
            DB::afterCommit(fn () => $this->idempotency->put($dto->idempotencyKey, $dto->initiatedBy, $dto->route, $hash, 201, ['data' => $payload, 'meta' => [], 'errors' => []]));

            return $payload;
        });
    }
}
