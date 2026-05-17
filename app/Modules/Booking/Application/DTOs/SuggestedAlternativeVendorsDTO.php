<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\DTOs;

final readonly class SuggestedAlternativeVendorsDTO
{
    /**
     * @param array<int> $vendorProfileIds
     */
    public function __construct(
        public int $bookingId,
        public int $adminId,
        public array $vendorProfileIds,
        public string $reason,
    ) {}
}
