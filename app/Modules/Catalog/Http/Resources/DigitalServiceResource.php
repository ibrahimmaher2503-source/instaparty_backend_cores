<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;

class DigitalServiceResource extends ServiceBaseResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $detail = $this->digitalDetail;

        return array_merge($this->baseFields(), [
            'digital' => [
                'delivery_method' => $detail?->delivery_method,
                'has_expiry' => $detail?->has_expiry,
                'expiry_days_after_purchase' => $detail?->expiry_days_after_purchase,
                'is_refundable_after_delivery' => $detail?->is_refundable_after_delivery,
                'redemption_url_template' => $detail?->redemption_url_template,
            ],
        ]);
    }
}
