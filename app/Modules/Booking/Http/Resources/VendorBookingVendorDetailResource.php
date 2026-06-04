<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Resources;

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAddress;
use App\Modules\Booking\Domain\Models\BookingVendor;
use Illuminate\Http\Request;

/**
 * Vendor-facing booking detail. Extends the list shape with booking context.
 *
 * Privacy rules (15_Critical_Risk_Audit):
 * - customer phone masked to last 4 digits
 * - no customer email
 * - street address only once the vendor has accepted; city-only while pending
 *
 * @mixin BookingVendor
 */
class VendorBookingVendorDetailResource extends BookingVendorResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $locale = str_starts_with($request->header('Accept-Language', 'ar'), 'en') ? 'en' : 'ar';
        $tz = auth()->user()->timezone ?? 'Africa/Cairo';

        /** @var BookingVendor $bookingVendor */
        $bookingVendor = $this->resource;

        /** @var Booking|null $booking */
        $booking = $bookingVendor->booking;

        return [
            ...parent::toArray($request),
            'booking' => $booking === null ? null : [
                'public_id' => $booking->public_id,
                'reference_no' => $booking->reference_no,
                'lifecycle_status' => $booking->lifecycle_status->getMorphClass(),
                'event_starts_at' => $booking->event_starts_at?->setTimezone($tz)->toIso8601String(),
                'event_ends_at' => $booking->event_ends_at?->setTimezone($tz)->toIso8601String(),
                'occasion' => $booking->occasion?->getTranslation('name', $locale),
                'customer_name' => $booking->customer?->name,
                'customer_phone_masked' => $this->maskPhone($booking->address?->recipient_phone_e164),
                'address' => $this->addressBlock($booking->address, $bookingVendor, $locale),
            ],
        ];
    }

    private function maskPhone(?string $phone): ?string
    {
        if ($phone === null || strlen($phone) < 4) {
            return null;
        }

        return str_repeat('*', max(0, strlen($phone) - 4)).substr($phone, -4);
    }

    /** @return array<string, mixed>|null */
    private function addressBlock(?BookingAddress $address, BookingVendor $bookingVendor, string $locale): ?array
    {
        if ($address === null) {
            return null;
        }

        $accepted = $bookingVendor->sub_status === VendorSubStatus::Accepted;

        return [
            'city' => $address->city?->getTranslation('name', $locale),
            // Street address is booking-specific data the vendor needs for
            // fulfilment — released only after the vendor commits.
            'address_line' => $accepted ? $address->address_line : null,
            'recipient_name' => $accepted ? $address->recipient_name : null,
        ];
    }
}
