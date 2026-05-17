<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Resources;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Booking
 *
 * @response {
 *   "data": {
 *     "public_id": "01HXY...",
 *     "reference_no": "INP-2026-000123",
 *     "lifecycle_status": "customer_review",
 *     "payment_status": "unpaid",
 *     "fulfillment_status": "not_started",
 *     "requires_customer_approval": true,
 *     "total_minor": 25000,
 *     "currency": "EGP"
 *   }
 * }
 */
class BookingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $tz = auth()->user()->timezone ?? 'Africa/Cairo';

        return [
            'public_id' => $this->public_id,
            'reference_no' => $this->reference_no,
            'lifecycle_status' => $this->lifecycle_status->getValue(),
            'payment_status' => $this->payment_status->getValue(),
            'fulfillment_status' => $this->fulfillment_status->value,
            'event_starts_at' => $this->event_starts_at?->setTimezone($tz)->toIso8601String(),
            'event_ends_at' => $this->event_ends_at?->setTimezone($tz)->toIso8601String(),
            'guest_count' => $this->guest_count,
            'subtotal_minor' => $this->subtotal_minor,
            'delivery_total_minor' => $this->delivery_total_minor,
            'discount_total_minor' => $this->discount_total_minor,
            'total_minor' => $this->total_minor,
            'currency' => $this->total_currency ?? 'EGP',
            'requires_customer_approval' => $this->requiresCustomerApproval(),
            'vendors' => BookingVendorResource::collection($this->whenLoaded('vendors')),
            'address' => $this->whenLoaded('address', function () {
                /** @var BookingAddress $address */
                $address = $this->address;

                return [
                    'city_id' => $address->city_id,
                    'address_line' => $address->address_line,
                    'building' => $address->building,
                    'floor' => $address->floor,
                    'apartment' => $address->apartment,
                    'landmark' => $address->landmark,
                    'recipient_name' => $address->recipient_name,
                    'recipient_phone_e164' => $address->recipient_phone_e164,
                ];
            }),
        ];
    }

    private function requiresCustomerApproval(): bool
    {
        /** @var Booking $booking */
        $booking = $this->resource;

        return $booking->pendingModifications()->exists();
    }
}
