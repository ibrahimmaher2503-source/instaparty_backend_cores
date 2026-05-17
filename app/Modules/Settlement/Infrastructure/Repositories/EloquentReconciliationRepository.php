<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Infrastructure\Repositories;

use App\Modules\Settlement\Application\DTOs\DetectedFinding;
use App\Modules\Settlement\Domain\Enums\ReconciliationStatus;
use App\Modules\Settlement\Domain\Models\ReconciliationFinding;
use App\Modules\Settlement\Domain\Models\ReconciliationRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class EloquentReconciliationRepository
{
    public function createRun(
        string $scopeType,
        ?array $scopeParams,
        string $triggerKind,
        ?int $triggeredByUserId,
        ?string $idempotencyKey,
        string $correlationId,
    ): ReconciliationRun {
        return ReconciliationRun::create([
            'public_id'             => Str::ulid()->toBase32(),
            'scope_type'            => $scopeType,
            'scope_params'          => $scopeParams,
            'status'                => ReconciliationStatus::Queued->value,
            'triggered_by_user_id'  => $triggeredByUserId,
            'trigger_kind'          => $triggerKind,
            'idempotency_key'       => $idempotencyKey,
            'correlation_id'        => $correlationId,
            'created_at'            => now(),
        ]);
    }

    public function markRunning(ReconciliationRun $run): void
    {
        $run->update([
            'status'     => ReconciliationStatus::Running->value,
            'started_at' => now(),
        ]);
    }

    public function recordFinding(ReconciliationRun $run, DetectedFinding $finding): ReconciliationFinding
    {
        return ReconciliationFinding::create([
            'public_id'              => Str::ulid()->toBase32(),
            'reconciliation_run_id'  => $run->id,
            'finding_type'           => $finding->findingType->value,
            'severity'               => $finding->severity->value,
            'resource_type'          => $finding->resourceType,
            'resource_id'            => $finding->resourceId,
            'expected'               => $finding->expected,
            'actual'                 => $finding->actual,
            'delta'                  => $finding->delta,
            'description_key'        => $finding->descriptionKey,
            'description_params'     => $finding->descriptionParams,
            'created_at'             => now(),
        ]);
    }

    public function finalise(ReconciliationRun $run, ReconciliationStatus $status, array $counts): void
    {
        $run->update(array_merge($counts, [
            'status'       => $status->value,
            'completed_at' => now(),
        ]));
    }
}
