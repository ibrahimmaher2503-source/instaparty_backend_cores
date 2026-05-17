<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Listeners;

use App\Modules\Settlement\Domain\Events\ReconciliationFindingRaised;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

class NotifyAdminOnHighSeverityFindingListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(ReconciliationFindingRaised $event): void
    {
        $adminUserIds = DB::table('users')
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('roles.name', 'admin')
            ->pluck('users.id')
            ->all();

        if (empty($adminUserIds)) {
            return;
        }

        $descriptionKey    = 'settlement.findings.' . $event->findingType->value;
        $descriptionParams = [
            'finding_type' => $event->findingType->value,
            'severity'     => $event->severity->value,
            'resource_type' => $event->resourceType ?? 'unknown',
            'resource_id'   => (string) ($event->resourceId ?? ''),
        ];

        foreach ($adminUserIds as $adminUserId) {
            DB::table('notification_dispatches')->insert([
                'public_id'      => (string) \Illuminate\Support\Str::ulid(),
                'user_id'        => $adminUserId,
                'channel'        => 'in_app',
                'status'         => 'sent',
                'context'        => json_encode([
                    'finding_id'    => $event->findingId,
                    'finding_type'  => $event->findingType->value,
                    'severity'      => $event->severity->value,
                    'resource_type' => $event->resourceType,
                    'resource_id'   => $event->resourceId,
                ]),
                'reference_type' => 'reconciliation_finding',
                'reference_id'   => $event->findingId,
                'sent_at'        => now(),
                'created_at'     => now(),
            ]);
        }
    }
}
