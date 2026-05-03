<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @response {
 *   "data": {
 *     "public_id": "01HZXXXXXXXXXXXXXXXXXXXXXX",
 *     "name": "Birthday Points",
 *     "terms": "Earn 1 point per EGP. 100 points = 10 EGP off.",
 *     "status": "active",
 *     "currency": "EGP",
 *     "expiration_days": null,
 *     "active_rule": {
 *       "public_id": "01HZXXXXXXXXXXXXXXXXXXXXXX",
 *       "label": "Standard Rate",
 *       "earn_points_per_minor": 1,
 *       "earn_minor_per_unit": 100,
 *       "redemption_ratio_points": 100,
 *       "redemption_ratio_minor": 1000,
 *       "min_points_to_redeem": 200,
 *       "max_redeem_pct_bps": 2000
 *     },
 *     "created_at": "2026-05-03T10:00:00Z"
 *   }
 * }
 * @response scenario="Arabic" {
 *   "data": {
 *     "public_id": "01HZXXXXXXXXXXXXXXXXXXXXXX",
 *     "name": "نقاط أعياد الميلاد",
 *     "terms": "اكسب نقطة لكل جنيه. 100 نقطة = خصم 10 جنيهات.",
 *     "status": "active",
 *     "currency": "EGP",
 *     "expiration_days": null,
 *     "active_rule": null,
 *     "created_at": "2026-05-03T10:00:00Z"
 *   }
 * }
 */
class LoyaltyProgramResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->header('Accept-Language', 'en');

        return [
            'public_id'       => $this->public_id,
            'name'            => $this->getTranslation('name', $locale, false) ?: $this->getTranslation('name', 'en', false),
            'terms'           => $this->terms ? ($this->getTranslation('terms', $locale, false) ?: $this->getTranslation('terms', 'en', false)) : null,
            'status'          => $this->status->value,
            'currency'        => $this->currency,
            'expiration_days' => $this->expiration_days,
            'active_rule'     => $this->whenLoaded('activeRule', fn () => $this->activeRule ? [
                'public_id'               => $this->activeRule->public_id,
                'label'                   => $this->activeRule->getTranslation('label', $locale, false) ?: $this->activeRule->getTranslation('label', 'en', false),
                'earn_points_per_minor'   => $this->activeRule->earn_points_per_minor,
                'earn_minor_per_unit'     => $this->activeRule->earn_minor_per_unit,
                'redemption_ratio_points' => $this->activeRule->redemption_ratio_points,
                'redemption_ratio_minor'  => $this->activeRule->redemption_ratio_minor,
                'min_points_to_redeem'    => $this->activeRule->min_points_to_redeem,
                'max_redeem_pct_bps'      => $this->activeRule->max_redeem_pct_bps,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
