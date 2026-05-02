<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Actions;

use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\Events\PaymentFailed;
use App\Modules\Payments\Domain\Models\GatewayWebhookLog;
use App\Modules\Payments\Infrastructure\Repositories\EloquentPaymentRepository;
use App\Modules\Payments\Infrastructure\Support\MapPaymobFailureCode;
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
        GatewayWebhookLog::query()->create([
            'gateway' => 'paymob',
            'event_type' => (string) ($payload['type'] ?? 'unknown'),
            'signature_valid' => $valid,
            'payload' => $payload,
            'created_at' => now(),
        ]);

        abort_unless($valid, 401, 'Invalid signature');

        $dto = $this->gateway->parseWebhook($payload);
        $payment = $this->payments->findByGatewayRef('paymob', $dto->gatewayRef);
        if ($payment === null) {
            return;
        }

        if ($dto->success) {
            $this->capture->execute($payment->id);
            return;
        }

        DB::transaction(function () use ($payment): void {
            $code = MapPaymobFailureCode::fromMessage($payment->failure_code);
            $message = ['en' => __('payments::failures.'.$code, [], 'en'), 'ar' => __('payments::failures.'.$code, [], 'ar')];
            $this->payments->markFailed($payment, $code, $message);
            DB::afterCommit(fn () => event(new PaymentFailed($payment->id, $payment->booking_id, $code, $message)));
        });
    }
}
