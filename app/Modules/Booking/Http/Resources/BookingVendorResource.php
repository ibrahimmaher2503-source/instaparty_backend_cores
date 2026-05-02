<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Resources;

use App\Modules\Booking\Domain\Models\BookingVendor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BookingVendor */
class BookingVendorResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'vendor_profile_id' => $this->vendor_profile_id,
            'sub_status' => $this->sub_status->value,
            'response_deadline' => $this->response_deadline?->toIso8601String(),
            'responded_at' => $this->responded_at?->toIso8601String(),
            'subtotal_minor' => $this->subtotal_minor,
            'delivery_fee_minor' => $this->delivery_fee_minor,
            'currency' => $this->subtotal_currency ?? 'EGP',
            'items' => BookingItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
