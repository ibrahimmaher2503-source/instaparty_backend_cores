<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\Booking\Domain\Models\BookingVendor */
class BookingVendorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'vendor_profile_id' => $this->vendor_profile_id,
            'sub_status' => $this->sub_status->value,
            'subtotal_minor' => $this->subtotal_minor,
            'delivery_fee_minor' => $this->delivery_fee_minor,
            'currency' => $this->subtotal_currency ?? 'EGP',
            'items' => BookingItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
