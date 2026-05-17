<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Listeners;

use App\Modules\Catalog\Domain\Events\ServiceChangeRequestApproved;
use App\Modules\Catalog\Domain\Events\ServiceChangeRequestClarificationReplied;
use App\Modules\Catalog\Domain\Events\ServiceChangeRequestClarificationRequested;
use App\Modules\Catalog\Domain\Events\ServiceChangeRequestRejected;
use App\Modules\Catalog\Domain\Events\ServiceChangeRequestSubmitted;
use App\Modules\Catalog\Domain\Models\ServiceChangeRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WriteServiceChangeRequestAuditListener
{
    public function handleSubmitted(ServiceChangeRequestSubmitted $event): void
    {
        $this->insert($event->changeRequest, 'submitted', $event->changeRequest->submitted_by, [
            'service_id' => $event->changeRequest->service_id,
            'product_type' => $event->changeRequest->product_type->value,
            'field_count' => $event->changeRequest->items()->count(),
        ]);
    }

    public function handleApproved(ServiceChangeRequestApproved $event): void
    {
        foreach ($event->appliedFieldPaths as $fieldPath) {
            $this->insert($event->changeRequest, 'field_applied', $event->changeRequest->decided_by, [
                'field_path' => $fieldPath,
                'service_id' => $event->changeRequest->service_id,
            ]);
        }
    }

    public function handleRejected(ServiceChangeRequestRejected $event): void
    {
        $this->insert($event->changeRequest, 'rejected', $event->changeRequest->decided_by, [
            'service_id' => $event->changeRequest->service_id,
            'admin_note' => $event->changeRequest->getRawOriginal('admin_note'),
        ]);
    }

    public function handleClarificationRequested(ServiceChangeRequestClarificationRequested $event): void
    {
        $this->insert($event->changeRequest, 'clarification_requested', $event->message->author_user_id, [
            'round' => $event->changeRequest->clarification_round,
            'message_id' => $event->message->id,
        ]);
    }

    public function handleClarificationReplied(ServiceChangeRequestClarificationReplied $event): void
    {
        $this->insert($event->changeRequest, 'clarification_replied', $event->message->author_user_id, [
            'round' => $event->message->clarification_round,
            'message_id' => $event->message->id,
        ]);
    }

    /** @param array<string, mixed> $changes */
    private function insert(ServiceChangeRequest $cr, string $action, ?int $userId, array $changes): void
    {
        DB::table('audit_logs')->insert([
            'public_id' => Str::ulid()->toString(),
            'auditable_type' => ServiceChangeRequest::class,
            'auditable_id' => $cr->id,
            'user_id' => $userId,
            'action' => $action,
            'changes' => json_encode($changes),
            'created_at' => now(),
        ]);
    }
}
