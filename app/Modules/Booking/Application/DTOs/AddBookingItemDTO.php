<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\DTOs;

use Carbon\Carbon;

final readonly class AddBookingItemDTO
{
    public function __construct(
        public int $bookingId,
        public int $customerId,
        public int $serviceId,
        public int $quantity,
        public ?Carbon $effectiveStartsAt,
        public ?Carbon $effectiveEndsAt,
        /** @var array<string, mixed>|null */
        public ?array $customizationData,
    ) {}
}
