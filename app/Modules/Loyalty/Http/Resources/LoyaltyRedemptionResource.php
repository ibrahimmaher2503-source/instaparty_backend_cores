<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @response {
 *   "data": {
 *     "public_id": "01HZXXXXXXXXXXXXXXXXXXXXXX",
 *     "points_held": 400,
 *     "discount_minor": 4000,
 *     "discount_formatted": "40.00 EGP",
 *     "discount_currency": "EGP",
 *     "status": "pending",
 *     "applied_at": null,
 *     "voided_at": null,
 *     "reversed_at": null,
 *     "created_at": "2026-05-03T10:00:00Z"
 *   }
 * }
 * @response scenario="Arabic applied" {
 *   "data": {
 *     "public_id": "01HZXXXXXXXXXXXXXXXXXXXXXX",
 *     "points_held": 400,
 *     "discount_minor": 4000,
 *     "discount_formatted": "٤٠٫٠٠ جنيه",
 *     "discount_currency": "EGP",
 *     "status": "applied",
 *     "applied_at": "2026-05-03T12:00:00Z",
 *     "voided_at": null,
 *     "reversed_at": null,
 *     "created_at": "2026-05-03T10:00:00Z"
 *   }
 * }
 */
class LoyaltyRedemptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'points_held' => $this->points_held,
            'discount_minor' => $this->discount_minor,
            'discount_formatted' => number_format($this->discount_minor / 100, 2).' '.$this->discount_currency,
            'discount_currency' => $this->discount_currency,
            'status' => (string) $this->status,
            'applied_at' => $this->applied_at?->toISOString(),
            'voided_at' => $this->voided_at?->toISOString(),
            'reversed_at' => $this->reversed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
