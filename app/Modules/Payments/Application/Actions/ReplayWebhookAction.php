<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Actions;

use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Models\GatewayWebhookLog;
use App\Modules\Payments\Infrastructure\Repositories\EloquentPaymentRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReplayWebhookAction
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly EloquentPaymentRepository $payments,
        private readonly CapturePaymentAction $capture,
    ) {}

    public function execute(int $webhookLogId, int $adminUserId): void
    {
        /** @var GatewayWebhookLog $log */
        $log = GatewayWebhookLog::query()->findOrFail($webhookLogId);

        // The signature was already verified when the log was first received.
        // Re-parse the stored payload and re-apply — idempotency is guaranteed
        // by the (gateway, gateway_ref) UNIQUE constraint on payments.
        $dto = $this->gateway->parseWebhook($log->payload);
        $payment = $this->payments->findByGatewayRef($log->gateway, $dto->gatewayRef);

        if ($payment !== null && $dto->success && $payment->status !== PaymentStatus::Captured) {
            $this->capture->execute($payment->id);
        }

        DB::afterCommit(fn () => DB::table('audit_logs')->insert([
            'public_id' => (string) Str::ulid(),
            'auditable_type' => GatewayWebhookLog::class,
            'auditable_id' => $log->id,
            'user_id' => $adminUserId,
            'action' => 'webhook_replayed',
            'changes' => json_encode(['gateway' => $log->gateway, 'event_type' => $log->event_type]),
            'created_at' => now(),
        ]));
    }
}
