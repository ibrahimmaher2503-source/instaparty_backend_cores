<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Events;

use App\Modules\Booking\Domain\Models\BookingModification;

final class VendorModificationProposed
{
    public function __construct(
        public readonly BookingModification $modification,
    ) {}
}
