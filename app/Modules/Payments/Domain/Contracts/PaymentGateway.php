<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Contracts;

use App\Modules\Payments\Application\DTOs\InitiatePaymentDto;
use App\Modules\Payments\Application\DTOs\PaymentIntentDto;
use App\Modules\Payments\Application\DTOs\PaymobWebhookDto;
use App\Modules\Payments\Application\DTOs\RefundResultDto;
use App\Modules\Payments\Domain\Models\Payment;
use Brick\Money\Money;

interface PaymentGateway
{
    public function initiate(InitiatePaymentDto $dto): PaymentIntentDto;

    public function verifyWebhookSignature(array $payload, string $signature): bool;

    public function parseWebhook(array $payload): PaymobWebhookDto;

    public function refund(Payment $payment, Money $amount): RefundResultDto;
}
