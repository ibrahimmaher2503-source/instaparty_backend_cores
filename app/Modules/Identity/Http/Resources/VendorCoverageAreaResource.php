<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Domain\Models\VendorCoverageArea;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorCoverageAreaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var VendorCoverageArea $area */
        $area = $this->resource;

        return [
            'city_id' => $area->city_id,
            'delivery_fee_minor' => $area->delivery_fee_minor,
            'delivery_fee_currency' => $area->delivery_fee_currency,
            'min_order_minor' => $area->min_order_minor,
            'min_order_currency' => $area->min_order_currency,
        ];
    }
}
