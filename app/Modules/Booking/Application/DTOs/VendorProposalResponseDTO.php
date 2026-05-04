<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\DTOs;

use App\Modules\Booking\Domain\Enums\CustomerConsentStatus;

final readonly class VendorProposalResponseDTO
{
    public function __construct(
        public string $interventionPublicId,
        public int $customerId,
        public CustomerConsentStatus $decision,
    ) {}
}
