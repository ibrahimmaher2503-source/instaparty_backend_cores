<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @response {
 *   "data": {
 *     "vendor_public_id": "01HZXXXXXXXXXXXXXXXXXXXXXX",
 *     "vendor_name": "Ibrahim's Party Shop",
 *     "available_points": 850,
 *     "held_points": 150,
 *     "total_points": 1000
 *   }
 * }
 * @response scenario="Arabic" {
 *   "data": {
 *     "vendor_public_id": "01HZXXXXXXXXXXXXXXXXXXXXXX",
 *     "vendor_name": "متجر إبراهيم للحفلات",
 *     "available_points": 850,
 *     "held_points": 150,
 *     "total_points": 1000
 *   }
 * }
 */
class LoyaltyBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->header('Accept-Language', 'en');

        return [
            'vendor_public_id' => $this->resource['vendor_public_id'],
            'vendor_name'      => $this->resource['vendor_name'][$locale] ?? $this->resource['vendor_name']['en'] ?? null,
            'available_points' => $this->resource['available_points'],
            'held_points'      => $this->resource['held_points'],
            'total_points'     => $this->resource['total_points'],
        ];
    }
}
