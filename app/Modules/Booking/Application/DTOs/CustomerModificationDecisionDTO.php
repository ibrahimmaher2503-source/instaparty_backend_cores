<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\DTOs;

final readonly class CustomerModificationDecisionDTO
{
    public function __construct(
        public int $bookingId,
        public int $customerId,
        public string $modificationPublicId,
        public string $decision,
        public string $idempotencyKey,
    ) {}
}
