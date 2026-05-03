<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Contracts;

use App\Modules\Settlement\Application\DTOs\BookingItemSnapshotDto;

interface SettlementBookingReader
{
    public function findItemById(int $id): ?BookingItemSnapshotDto;

    /**
     * @return BookingItemSnapshotDto[]
     */
    public function itemsForPayment(int $bookingId): array;
}
