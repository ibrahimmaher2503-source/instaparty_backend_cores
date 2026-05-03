<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Gateways;

use App\Modules\Communication\Domain\Contracts\NotificationChannelAdapter;
use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use Illuminate\Support\Facades\Log;
use MailchimpMarketing\ApiClient;
use Throwable;

class MailchimpEmailAdapter implements NotificationChannelAdapter
{
    public function __construct(
        private readonly ApiClient $mailchimp,
    ) {}

    public function send(NotificationDispatch $dispatch): void
    {
        $context = $dispatch->context ?? [];
        $to = $context['email'] ?? null;
        $subject = $context['subject'] ?? '';
        $body = $context['email_body'] ?? $context['body'] ?? '';

        if (! $to) {
            $dispatch->status = DispatchStatus::Failed;
            $dispatch->error_message = 'No email in context';
            $dispatch->save();

            return;
        }

        try {
            $response = $this->mailchimp->messages->send([
                'message' => [
                    'html' => $body,
                    'subject' => $subject,
                    'from_email' => config('mail.from.address'),
                    'from_name' => config('mail.from.name'),
                    'to' => [['email' => $to, 'type' => 'to']],
                ],
            ]);

            $result = $response[0] ?? null;
            $status = $result['status'] ?? 'error';

            if (in_array($status, ['sent', 'queued'], true)) {
                $dispatch->status = DispatchStatus::Sent;
                $dispatch->provider = 'mailchimp';
                $dispatch->provider_ref = $result['_id'] ?? null;
                $dispatch->sent_at = now();
            } else {
                $dispatch->status = DispatchStatus::Failed;
                $dispatch->error_message = 'Mailchimp status: '.$status.' — '.($result['reject_reason'] ?? '');
            }

            $dispatch->save();
        } catch (Throwable $e) {
            $dispatch->status = DispatchStatus::Failed;
            $dispatch->error_message = $e->getMessage();
            $dispatch->save();
            Log::error('MailchimpEmailAdapter error', ['dispatch_id' => $dispatch->id, 'error' => $e->getMessage()]);
        }
    }
}
