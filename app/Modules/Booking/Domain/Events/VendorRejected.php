<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Events;

use App\Modules\Booking\Domain\Models\BookingVendor;

final class VendorRejected
{
    public function __construct(
        public readonly BookingVendor $bookingVendor,
    ) {}
}
