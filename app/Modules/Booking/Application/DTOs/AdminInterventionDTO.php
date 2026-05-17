<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\DTOs;

use App\Modules\Booking\Domain\Enums\InterventionType;
use Carbon\Carbon;

final readonly class AdminInterventionDTO
{
    public function __construct(
        public int $bookingId,
        public int $adminId,
        public InterventionType $interventionType,
        public string $reason,
        public ?int $proposedVendorId = null,
        public ?Carbon $consentExpiresAt = null,
        public ?int $bookingVendorId = null,
        public ?array $suggestedVendorIds = null,
    ) {}
}
