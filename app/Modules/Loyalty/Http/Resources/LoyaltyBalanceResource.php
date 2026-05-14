<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoyaltyBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = str_starts_with((string) $request->header('Accept-Language', app()->getLocale()), 'ar') ? 'ar' : 'en';

        $name = $this->resource['vendor_name'] ?? null;
        if (is_array($name)) {
            $name = $name[$locale] ?? ($name['en'] ?? null);
        }

        return [
            'vendor_public_id' => $this->resource['vendor_public_id'],
            'vendor_name' => $name,
            'available_points' => (int) ($this->resource['available_points'] ?? 0),
            'held_points' => (int) ($this->resource['held_points'] ?? 0),
            'total_points' => (int) ($this->resource['total_points'] ?? 0),
        ];
    }
}
