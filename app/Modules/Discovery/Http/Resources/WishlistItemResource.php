<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Http\Resources;

use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WishlistItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->header('Accept-Language', 'en') === 'ar' ? 'ar' : 'en';
        $service = $this->service;

        return [
            'service_id' => $service?->public_id,
            'name' => $service?->getTranslation('name', $locale),
            'product_type' => $service?->product_type?->value,
            'base_price_minor' => $service?->base_price_minor,
            'base_price_currency' => $service?->base_price_currency ?? 'EGP',
            'is_available' => $service?->status === ServiceStatus::Published,
            'added_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
