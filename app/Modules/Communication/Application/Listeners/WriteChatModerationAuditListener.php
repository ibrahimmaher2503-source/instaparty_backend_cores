<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Listeners;

use App\Modules\Shared\Domain\Events\AuditableEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Single listener subscribed to all six chat-moderation events.
 *
 * Writes exactly one `audit_logs` row per event (Constitution §VII, R6).
 * Idempotent: each event maps to exactly one INSERT; no UPDATEs.
 */
class WriteChatModerationAuditListener
{
    public function handle(AuditableEvent $event): void
    {
        $auditable = $event->auditable();
        $actor = $event->actor();

        DB::table('audit_logs')->insert([
            'public_id' => Str::ulid()->toBase32(),
            'auditable_type' => $auditable::class,
            'auditable_id' => (int) $auditable->getKey(),
            'user_id' => $actor?->getKey(),
            'action' => $event->action(),
            'changes' => json_encode($event->changes(), JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ]);
    }
}
