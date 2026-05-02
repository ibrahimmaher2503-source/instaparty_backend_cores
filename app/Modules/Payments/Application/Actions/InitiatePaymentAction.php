<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Actions;

use App\Modules\Payments\Application\DTOs\InitiatePaymentDto;
use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Events\PaymentInitiated;
use App\Modules\Payments\Infrastructure\Repositories\EloquentIdempotencyKeyRepository;
use App\Modules\Payments\Infrastructure\Repositories\EloquentPaymentRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InitiatePaymentAction
{
    public function __construct(
        private readonly EloquentPaymentRepository $payments,
        private readonly EloquentIdempotencyKeyRepository $idempotency,
        private readonly PaymentGateway $gateway,
    ) {}

    public function execute(InitiatePaymentDto $dto): array
    {
        $hash = hash('sha256', implode('|', [$dto->bookingId, $dto->payerId, $dto->method->value, $dto->amount->getMinorAmount()->toInt()]));
        $cached = $this->idempotency->lookup($dto->idempotencyKey, $dto->payerId, $dto->route);
        if ($cached !== null) {
            if ($cached->request_hash !== $hash) {
                abort(409, 'Idempotency key conflict');
            }

            return $cached->response_body ?? [];
        }

        return DB::transaction(function () use ($dto, $hash): array {
            $intent = $this->gateway->initiate($dto);

            $payment = $this->payments->create([
                'public_id' => (string) Str::ulid(),
                'booking_id' => $dto->bookingId,
                'user_id' => $dto->payerId,
                'gateway' => 'paymob',
                'gateway_ref' => $intent->gatewayRef,
                'amount_minor' => $dto->amount->getMinorAmount()->toInt(),
                'amount_currency' => $dto->amount->getCurrency()->getCurrencyCode(),
                'method' => $dto->method,
                'status' => PaymentStatus::Pending,
                'metadata' => $intent->rawResponse,
            ]);

            $payload = ['payment_public_id' => $payment->public_id, 'redirect_url' => $intent->redirectUrl, 'status' => $payment->status->value];

            DB::afterCommit(function () use ($payment, $payload, $dto, $hash): void {
                event(new PaymentInitiated($payment->id, $payment->booking_id, $payment->user_id, $payment->amount_minor, $payment->amount_currency));
                $this->idempotency->put($dto->idempotencyKey, $dto->payerId, $dto->route, $hash, 201, ['data' => $payload, 'meta' => [], 'errors' => []]);
            });

            return $payload;
        });
    }
}
