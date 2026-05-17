<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Actions;

use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\Events\PaymentFailed;
use App\Modules\Payments\Domain\Models\GatewayWebhookLog;
use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Payments\Infrastructure\Repositories\EloquentPaymentRepository;
use App\Modules\Payments\Infrastructure\Support\MapPaymobFailureCode;
use App\Modules\Payments\Infrastructure\Support\RedactPciFields;
use App\Modules\Shared\Application\Services\IdempotencyService;
use Illuminate\Support\Facades\DB;

class ProcessPaymobWebhookAction
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly EloquentPaymentRepository $payments,
        private readonly CapturePaymentAction $capture,
        private readonly ProcessRefundAction $processRefund,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function execute(array $payload, string $signature): void
    {
        $valid = $this->gateway->verifyWebhookSignature($payload, $signature);
        $log = GatewayWebhookLog::query()->create([
            'gateway' => 'paymob',
            'event_type' => (string) ($payload['type'] ?? 'unknown'),
            'signature_valid' => $valid,
            'payload' => RedactPciFields::redact($payload),
            'created_at' => now(),
        ]);

        abort_unless($valid, 401, 'Invalid signature');

        $dto = $this->gateway->parseWebhook($payload);

        // Derive a deterministic idempotency key from the stable webhook fields.
        // This makes duplicate/replayed webhooks unconditionally idempotent.
        $obj           = $payload['obj'] ?? [];
        $eventType     = (string) ($payload['type'] ?? 'unknown');
        $orderId       = (string) ($obj['order']['id'] ?? $obj['order_id'] ?? '');
        $transactionId = (string) ($obj['id'] ?? '');
        $success       = $dto->success ? 'true' : 'false';
        $idempotencyKey = "paymob:{$eventType}:{$orderId}:{$transactionId}:{$success}";

        // Short-circuit duplicate webhooks; the callback returns the stored result.
        $alreadyProcessed = $this->idempotency->hasBeenUsed('internal_webhook', $idempotencyKey);
        if ($alreadyProcessed) {
            $log->update(['processing_error' => 'duplicate_idempotent_replay']);

            return;
        }

        $payment = $this->payments->findByGatewayRef('paymob', $dto->gatewayRef);
        if ($payment === null) {
            $log->update(['processing_error' => 'Unknown gateway_ref: ' . $dto->gatewayRef]);

            return;
        }

        // Route refund callbacks to the refund processing path
        if ($dto->transactionType === 'REFUND') {
            $refundIdempotencyKey = "paymob_refund:{$transactionId}";

            if (! $this->idempotency->hasBeenUsed('internal_webhook', $refundIdempotencyKey)) {
                $refund = Refund::query()->where('gateway_ref', $dto->gatewayRef)->first()
                    ?? Refund::query()
                        ->where('payment_id', $payment->id)
                        ->whereIn('status', ['pending', 'processing'])
                        ->latest()
                        ->first();

                if ($refund !== null) {
                    $this->processRefund->execute($refund->id);
                    $this->idempotency->remember('internal_webhook', $refundIdempotencyKey, $refundIdempotencyKey, fn () => true);
                    $log->update(['processed_at' => now()]);
                } else {
                    $log->update(['processing_error' => "No pending refund found for payment {$payment->id}"]);
                }
            } else {
                $log->update(['processing_error' => 'duplicate_refund_idempotent_replay']);
            }

            $this->idempotency->remember('internal_webhook', $idempotencyKey, $idempotencyKey, fn () => true);

            return;
        }

        if ($dto->success) {
            $this->capture->execute(
                paymentId: $payment->id,
                idempotencyKey: "capture:{$payment->id}",
            );
            $log->update(['processed_at' => now()]);

            // Record idempotency usage so replays short-circuit
            $this->idempotency->remember('internal_webhook', $idempotencyKey, $idempotencyKey, fn () => true);

            return;
        }

        DB::transaction(function () use ($payment, $log): void {
            $code = MapPaymobFailureCode::fromMessage($payment->failure_code);
            $message = ['en' => __('payments::failures.' . $code, [], 'en'), 'ar' => __('payments::failures.' . $code, [], 'ar')];
            $this->payments->markFailed($payment, $code, $message);
            $log->update(['processed_at' => now()]);
            DB::afterCommit(fn () => event(new PaymentFailed($payment->id, $payment->booking_id, $code, $message)));
        });

        $this->idempotency->remember('internal_webhook', $idempotencyKey, $idempotencyKey, fn () => true);
    }
}
