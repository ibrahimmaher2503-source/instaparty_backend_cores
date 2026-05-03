<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Gateways;

use App\Modules\Communication\Domain\Contracts\NotificationChannelAdapter;
use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Throwable;

class FcmPushAdapter implements NotificationChannelAdapter
{
    public function __construct(
        private readonly Messaging $messaging,
    ) {}

    public function send(NotificationDispatch $dispatch): void
    {
        $context = $dispatch->context ?? [];
        $token = $context['device_token'] ?? null;

        if (! $token) {
            $dispatch->status = DispatchStatus::Failed;
            $dispatch->error_message = 'No device token in context';
            $dispatch->save();

            return;
        }

        try {
            $message = CloudMessage::withTarget('token', $token)
                ->withNotification(
                    Notification::create(
                        $context['title'] ?? '',
                        $context['body'] ?? '',
                    )
                )
                ->withData($context['data'] ?? []);

            $result = $this->messaging->send($message);

            $dispatch->status = DispatchStatus::Sent;
            $dispatch->provider = 'fcm';
            $dispatch->provider_ref = $result;
            $dispatch->sent_at = now();
            $dispatch->save();
        } catch (Throwable $e) {
            $dispatch->status = DispatchStatus::Failed;
            $dispatch->error_message = $e->getMessage();
            $dispatch->save();
        }
    }
}
