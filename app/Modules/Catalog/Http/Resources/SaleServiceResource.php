<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;

class SaleServiceResource extends ServiceBaseResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $detail = $this->saleDetail;

        return array_merge($this->baseFields(), [
            'sale' => [
                'is_perishable' => $detail?->is_perishable,
                'is_made_to_order' => $detail?->is_made_to_order,
                'lead_time_hours' => $detail?->lead_time_hours,
                'stock_quantity' => $detail?->stock_quantity,
                'customization_fields' => $detail?->customization_fields,
            ],
        ]);
    }
}
