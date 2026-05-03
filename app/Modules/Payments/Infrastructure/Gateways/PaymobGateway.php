<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Gateways;

use App\Modules\Payments\Application\DTOs\InitiatePaymentDto;
use App\Modules\Payments\Application\DTOs\PaymentIntentDto;
use App\Modules\Payments\Application\DTOs\PaymobWebhookDto;
use App\Modules\Payments\Application\DTOs\RefundResultDto;
use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Infrastructure\Support\RedactPciFields;
use Brick\Money\Money;
use Illuminate\Support\Str;

class PaymobGateway implements PaymentGateway
{
    public function initiate(InitiatePaymentDto $dto): PaymentIntentDto
    {
        $gatewayRef = 'PMB-'.(string) Str::ulid();

        return new PaymentIntentDto(
            gatewayRef: $gatewayRef,
            redirectUrl: 'https://accept.paymob.com/unifiedcheckout/?publicKey=dummy&clientSecret='.$gatewayRef,
            rawResponse: RedactPciFields::redact(['gateway_ref' => $gatewayRef]),
        );
    }

    public function verifyWebhookSignature(array $payload, string $signature): bool
    {
        $secret = (string) config('services.paymob.hmac_secret', 'test');
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        $expected = hash_hmac('sha512', $json, $secret);

        return hash_equals($expected, $signature);
    }

    public function parseWebhook(array $payload): PaymobWebhookDto
    {
        $obj = $payload['obj'] ?? [];
        $amountCents = (int) ($obj['amount_cents'] ?? 0);
        $success = (bool) ($obj['success'] ?? false);

        return new PaymobWebhookDto(
            gatewayRef: (string) ($obj['id'] ?? ''),
            success: $success,
            failureCode: $success ? null : 'declined_by_issuer',
            capturedAmount: Money::ofMinor($amountCents, 'EGP'),
            rawPayload: RedactPciFields::redact($payload),
        );
    }

    public function refund(Payment $payment, Money $amount): RefundResultDto
    {
        return new RefundResultDto(
            success: true,
            gatewayRef: 'PMB-RFD-'.(string) Str::ulid(),
            failureMessage: null,
        );
    }
}
