<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\DTOs;

final readonly class SubmitBookingDTO
{
    public function __construct(
        public int $bookingId,
        public int $customerId,
        public string $idempotencyKey,
    ) {}
}
