<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Events;

use App\Modules\Booking\Domain\Models\BookingModification;

final class CustomerModificationDecided
{
    public function __construct(
        public readonly BookingModification $modification,
        public readonly string $decision,
    ) {}
}
