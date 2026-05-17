<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Http\Resources;

use App\Modules\Settlement\Domain\Models\ReconciliationRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReconciliationRun
 *
 * @response {
 *   "data": {
 *     "public_id": "01JVXXXXXXXXXXXXXXXXXXXXXX",
 *     "scope_type": "all",
 *     "scope_params": {},
 *     "status": "clean",
 *     "trigger_kind": "scheduled",
 *     "wallets_scanned": 42,
 *     "findings_count": 0,
 *     "auto_repaired_count": 0,
 *     "manual_review_count": 0,
 *     "started_at": "2026-05-16T04:00:00Z",
 *     "completed_at": "2026-05-16T04:01:12Z"
 *   }
 * }
 */
class ReconciliationRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'public_id'           => $this->public_id,
            'scope_type'          => $this->scope_type,
            'scope_params'        => $this->scope_params,
            'status'              => $this->status->value,
            'trigger_kind'        => $this->trigger_kind,
            'wallets_scanned'     => $this->wallets_scanned,
            'findings_count'      => $this->findings_count,
            'auto_repaired_count' => $this->auto_repaired_count,
            'manual_review_count' => $this->manual_review_count,
            'started_at'          => $this->started_at?->toISOString(),
            'completed_at'        => $this->completed_at?->toISOString(),
        ];
    }
}
