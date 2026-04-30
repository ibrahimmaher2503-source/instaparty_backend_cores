<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\Booking\Domain\Models\BookingItem */
class BookingItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = str_starts_with($request->header('Accept-Language', 'ar'), 'en') ? 'en' : 'ar';
        $name = $this->name_snapshot ?? [];

        return [
            'public_id' => $this->public_id,
            'product_type' => $this->product_type->value,
            'name' => $name[$locale] ?? $name['en'] ?? '',
            'unit_price_minor' => $this->unit_price_minor,
            'quantity' => $this->quantity,
            'line_total_minor' => $this->line_total_minor,
            'currency' => $this->unit_price_currency ?? 'EGP',
            'item_status' => $this->item_status,
            'effective_starts_at' => $this->effective_starts_at?->toIso8601String(),
            'effective_ends_at' => $this->effective_ends_at?->toIso8601String(),
        ];
    }
}
