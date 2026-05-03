<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Gateways;

use App\Modules\Communication\Domain\Contracts\NotificationChannelAdapter;
use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use Illuminate\Support\Facades\Log;

/**
 * Phase 1 stub — no external API calls.
 * Replace with real WhatsApp Cloud API adapter in Phase 1.5 (ADR-0010 §6.2).
 */
class WhatsAppStubAdapter implements NotificationChannelAdapter
{
    public function send(NotificationDispatch $dispatch): void
    {
        Log::debug('WhatsAppStubAdapter: stub dispatch (no API call)', [
            'dispatch_id' => $dispatch->id,
            'context' => $dispatch->context,
        ]);

        $dispatch->status = DispatchStatus::Sent;
        $dispatch->provider = 'whatsapp_stub';
        $dispatch->sent_at = now();
        $dispatch->save();
    }
}
