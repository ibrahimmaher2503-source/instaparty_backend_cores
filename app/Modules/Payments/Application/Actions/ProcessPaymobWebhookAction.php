<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Actions;

use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\Events\PaymentFailed;
use App\Modules\Payments\Domain\Models\GatewayWebhookLog;
use App\Modules\Payments\Infrastructure\Repositories\EloquentPaymentRepository;
use App\Modules\Payments\Infrastructure\Support\MapPaymobFailureCode;
use App\Modules\Payments\Infrastructure\Support\RedactPciFields;
use Illuminate\Support\Facades\DB;

class ProcessPaymobWebhookAction
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly EloquentPaymentRepository $payments,
        private readonly CapturePaymentAction $capture,
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
        $payment = $this->payments->findByGatewayRef('paymob', $dto->gatewayRef);
        if ($payment === null) {
            $log->update(['processing_error' => 'Unknown gateway_ref: '.$dto->gatewayRef]);

            return;
        }

        if ($dto->success) {
            $this->capture->execute($payment->id);
            $log->update(['processed_at' => now()]);

            return;
        }

        DB::transaction(function () use ($payment, $log): void {
            $code = MapPaymobFailureCode::fromMessage($payment->failure_code);
            $message = ['en' => __('payments::failures.'.$code, [], 'en'), 'ar' => __('payments::failures.'.$code, [], 'ar')];
            $this->payments->markFailed($payment, $code, $message);
            $log->update(['processed_at' => now()]);
            DB::afterCommit(fn () => event(new PaymentFailed($payment->id, $payment->booking_id, $code, $message)));
        });
    }
}
