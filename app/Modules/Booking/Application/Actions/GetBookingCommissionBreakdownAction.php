<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class GetBookingCommissionBreakdownAction
{
    /**
     * @return array{
     *     items: Collection,
     *     gross_total_minor: int,
     *     delivery_fee_minor: int,
     *     commission_total_minor: int,
     *     net_total_minor: int,
     *     currency: string,
     * }
     */
    public function execute(BookingVendor $bookingVendor, VendorProfile $vendorProfile): array
    {
        if ($bookingVendor->vendor_profile_id !== $vendorProfile->id) {
            throw ValidationException::withMessages(['booking' => 'Access denied.']);
        }

        $bookingVendor->loadMissing('items');

        return [
            'items'                  => $bookingVendor->items,
            'gross_total_minor'      => $bookingVendor->subtotal_minor,
            'delivery_fee_minor'     => $bookingVendor->delivery_fee_minor,
            'commission_total_minor' => $bookingVendor->commission_minor,
            'net_total_minor'        => $bookingVendor->vendor_payout_minor,
            'currency'               => $bookingVendor->subtotal_currency ?? 'EGP',
        ];
    }
}
