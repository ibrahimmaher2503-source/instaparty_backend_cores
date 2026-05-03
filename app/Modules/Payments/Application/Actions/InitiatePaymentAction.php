<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Actions;

use App\Modules\Payments\Application\DTOs\InitiatePaymentDto;
use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Events\PaymentInitiated;
use App\Modules\Payments\Infrastructure\Repositories\EloquentPaymentRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InitiatePaymentAction
{
    public function __construct(
        private readonly EloquentPaymentRepository $payments,
        private readonly PaymentGateway $gateway,
    ) {}

    public function execute(InitiatePaymentDto $dto): array
    {
        return DB::transaction(function () use ($dto): array {
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

            $payload = [
                'payment_public_id' => $payment->public_id,
                'redirect_url' => $intent->redirectUrl,
                'status' => $payment->status->value,
            ];

            DB::afterCommit(fn () => event(new PaymentInitiated(
                $payment->id,
                $payment->booking_id,
                $payment->user_id,
                $payment->amount_minor,
                $payment->amount_currency,
            )));

            return $payload;
        });
    }
}
