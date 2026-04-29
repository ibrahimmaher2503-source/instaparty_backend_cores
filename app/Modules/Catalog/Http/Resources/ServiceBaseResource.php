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
            // TODO: expose category public_id after eager-loading in action
            'status' => $this->status->value,
            'base_price' => [
                'minor' => $this->base_price_minor,
                'currency' => $this->base_price_currency,
                'display' => \Brick\Money\Money::ofMinor($this->base_price_minor, $this->base_price_currency)->formatTo(app()->getLocale()),
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
