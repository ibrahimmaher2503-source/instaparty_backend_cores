<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Gateways;

use App\Modules\Communication\Domain\Contracts\NotificationChannelAdapter;
use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use Illuminate\Support\Facades\Log;
use Throwable;
use Vonage\Client;
use Vonage\SMS\Message\SMS;

class VonageSmsAdapter implements NotificationChannelAdapter
{
    public function __construct(
        private readonly Client $vonageClient,
    ) {}

    public function send(NotificationDispatch $dispatch): void
    {
        $context = $dispatch->context ?? [];
        $to = $context['phone_e164'] ?? null;
        $body = $context['sms_body'] ?? $context['body'] ?? '';

        if (! $to) {
            $dispatch->status = DispatchStatus::Failed;
            $dispatch->error_message = 'No phone_e164 in context';
            $dispatch->save();

            return;
        }

        try {
            $message = new SMS($to, config('services.vonage.sms_from', 'InstaParty'), $body);
            $response = $this->vonageClient->sms()->send($message);
            $sent = $response->current();

            if ($sent->getStatus() === 0) {
                $dispatch->status = DispatchStatus::Sent;
                $dispatch->provider = 'vonage';
                $dispatch->provider_ref = $sent->getMessageId();
                $dispatch->sent_at = now();
            } else {
                $dispatch->status = DispatchStatus::Failed;
                $dispatch->error_message = 'Vonage status: '.$sent->getStatus();
            }

            $dispatch->save();
        } catch (Throwable $e) {
            $dispatch->status = DispatchStatus::Failed;
            $dispatch->error_message = $e->getMessage();
            $dispatch->save();
            Log::error('VonageSmsAdapter error', ['dispatch_id' => $dispatch->id, 'error' => $e->getMessage()]);
        }
    }
}
