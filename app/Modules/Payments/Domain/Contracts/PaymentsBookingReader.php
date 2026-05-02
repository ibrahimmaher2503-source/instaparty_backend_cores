<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Contracts;

use App\Modules\Payments\Application\DTOs\PaymentBookingReadDto;

interface PaymentsBookingReader
{
    public function findByPublicId(string $ulid): ?PaymentBookingReadDto;

    /** @return array<int, \App\Modules\Payments\Application\DTOs\PaymentBookingItemReadDto> */
    public function itemsFor(int $bookingId): array;

    /** @return array<int> */
    public function staleHoldBookingIds(): array;
}
