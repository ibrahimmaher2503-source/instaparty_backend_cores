<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\Booking\Domain\Models\Booking */
class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $tz = auth()->user()->timezone ?? 'Africa/Cairo';

        return [
            'public_id' => $this->public_id,
            'reference_no' => $this->reference_no,
            'lifecycle_status' => $this->lifecycle_status->value,
            'payment_status' => $this->payment_status->value,
            'fulfillment_status' => $this->fulfillment_status->value,
            'event_starts_at' => $this->event_starts_at?->setTimezone($tz)->toIso8601String(),
            'event_ends_at' => $this->event_ends_at?->setTimezone($tz)->toIso8601String(),
            'guest_count' => $this->guest_count,
            'subtotal_minor' => $this->subtotal_minor,
            'delivery_total_minor' => $this->delivery_total_minor,
            'discount_total_minor' => $this->discount_total_minor,
            'total_minor' => $this->total_minor,
            'currency' => $this->total_currency ?? 'EGP',
            'vendors' => BookingVendorResource::collection($this->whenLoaded('vendors')),
            'address' => $this->whenLoaded('address', fn () => [
                'city_id' => $this->address->city_id,
                'address_line' => $this->address->address_line,
                'building' => $this->address->building,
                'floor' => $this->address->floor,
                'apartment' => $this->address->apartment,
                'landmark' => $this->address->landmark,
                'recipient_name' => $this->address->recipient_name,
                'recipient_phone_e164' => $this->address->recipient_phone_e164,
            ]),
        ];
    }
}
