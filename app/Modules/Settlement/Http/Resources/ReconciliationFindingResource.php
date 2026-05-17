<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Http\Resources;

use App\Modules\Settlement\Domain\Models\ReconciliationFinding;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReconciliationFinding
 *
 * @response {
 *   "data": {
 *     "public_id": "01JVXXXXXXXXXXXXXXXXXXXXXX",
 *     "finding_type": "wallet_cache_drift",
 *     "severity": "warning",
 *     "resource_type": "App\\Modules\\Settlement\\Domain\\Models\\Wallet",
 *     "resource_id": 7,
 *     "resolution": "auto_repaired",
 *     "detected_at": "2026-05-16T04:00:45Z"
 *   }
 * }
 */
class ReconciliationFindingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'public_id'     => $this->public_id,
            'finding_type'  => $this->finding_type->value,
            'severity'      => $this->severity->value,
            'resource_type' => $this->resource_type,
            'resource_id'   => $this->resource_id,
            'expected'      => $this->expected,
            'actual'        => $this->actual,
            'delta'         => $this->delta,
            'resolution'    => $this->resolution,
            'detected_at'   => $this->created_at?->toISOString(),
        ];
    }
}
