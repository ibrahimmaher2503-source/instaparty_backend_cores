<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Pagination\LengthAwarePaginator;

class ListVendorIncomingBookingsAction
{
    public function execute(VendorProfile $vendorProfile, int $perPage = 25): LengthAwarePaginator
    {
        return BookingVendor::query()
            ->where('vendor_profile_id', $vendorProfile->id)
            ->where('sub_status', VendorSubStatus::Pending)
            ->with(['booking', 'booking.address', 'items'])
            ->latest('response_deadline')
            ->paginate($perPage);
    }
}
