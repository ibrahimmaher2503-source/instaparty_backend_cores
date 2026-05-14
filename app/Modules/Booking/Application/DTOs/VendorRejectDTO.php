<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\DTOs;

final readonly class VendorRejectDTO
{
    /**
     * @param  array<string,string>|null  $rejectionReason
     */
    public function __construct(
        public int $bookingVendorId,
        public int $vendorProfileId,
        public int $proposedByUserId,
        public ?array $rejectionReason = null,
        public ?string $idempotencyKey = null,
    ) {}
}
