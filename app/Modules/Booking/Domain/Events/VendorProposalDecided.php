<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Events;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;

final class VendorProposalDecided
{
    public function __construct(
        public readonly Booking $booking,
        public readonly BookingAdminIntervention $intervention,
        public readonly bool $accepted,
    ) {}
}
