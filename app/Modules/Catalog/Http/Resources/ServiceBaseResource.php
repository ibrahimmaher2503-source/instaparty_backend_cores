<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceBaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    protected function baseFields(): array
    {
        return [
            'public_id' => $this->public_id,
            'product_type' => $this->product_type->value,
            'name' => $this->getTranslation('name', app()->getLocale()),
            'short_description' => $this->getTranslation('short_description', app()->getLocale()),
            'category_id' => $this->category_id,
            'status' => $this->status->value,
            'base_price' => [
                'minor' => $this->base_price_minor,
                'currency' => $this->base_price_currency,
                'display' => number_format($this->base_price_minor / 100, 2).' '.$this->base_price_currency,
            ],
            'is_featured' => $this->is_featured,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return $this->baseFields();
    }
}
