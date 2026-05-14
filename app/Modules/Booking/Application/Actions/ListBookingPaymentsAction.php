<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Payments\Domain\Models\Payment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class ListBookingPaymentsAction
{
    /** @return Collection<int, Payment> */
    public function execute(BookingVendor $bookingVendor, VendorProfile $vendorProfile): Collection
    {
        if ($bookingVendor->vendor_profile_id !== $vendorProfile->id) {
            throw ValidationException::withMessages(['booking' => 'Access denied.']);
        }

        return Payment::query()
            ->where('booking_id', $bookingVendor->booking_id)
            ->with(['booking.customer'])
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
