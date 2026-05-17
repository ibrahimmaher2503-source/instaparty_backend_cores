<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\DTOs;

final readonly class CreateBookingModificationDTO
{
    public function __construct(
        public int $bookingVendorId,
        public int $vendorProfileId,
        public int $proposedByUserId,
    ) {}
}
