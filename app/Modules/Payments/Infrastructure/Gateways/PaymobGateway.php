<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Gateways;

use App\Modules\Payments\Application\DTOs\InitiatePaymentDto;
use App\Modules\Payments\Application\DTOs\PaymentIntentDto;
use App\Modules\Payments\Application\DTOs\PaymobWebhookDto;
use App\Modules\Payments\Application\DTOs\PingResult;
use App\Modules\Payments\Application\DTOs\RefundResultDto;
use App\Modules\Payments\Application\DTOs\VoidResult;
use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\Models\GatewayWebhookLog;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Infrastructure\Support\RedactPciFields;
use Brick\Money\Money;
use Illuminate\Support\Carbon;
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

    public function void(string $gatewayRef): VoidResult
    {
        // Production: POST /api/acceptance/void_refund/void with gateway transaction order ID
        return new VoidResult(
            success: true,
            gatewayRef: $gatewayRef,
        );
    }

    public function ping(): PingResult
    {
        $start = microtime(true);
        $healthOrderId = config('services.paymob.health_check_order_id');

        if (! $healthOrderId) {
            return new PingResult(
                success: false,
                latencyMs: 0,
                gatewayCode: 'paymob',
                errorMessage: 'health_check_order_id_not_configured',
            );
        }

        // Production: GET /api/ecommerce/orders/{id} with health_check_order_id
        $latencyMs = (int) round((microtime(true) - $start) * 1000);

        return new PingResult(
            success: true,
            latencyMs: $latencyMs,
            gatewayCode: 'paymob',
        );
    }

    public function getTodayCapturedCount(): int
    {
        // Production: call Paymob transaction report API filtered to today + CAPTURE type
        // Fallback: count local gateway_webhook_logs processed today
        return GatewayWebhookLog::query()
            ->where('event_type', 'transaction_processed')
            ->whereNotNull('processed_at')
            ->whereDate('created_at', Carbon::today())
            ->count();
    }
}
