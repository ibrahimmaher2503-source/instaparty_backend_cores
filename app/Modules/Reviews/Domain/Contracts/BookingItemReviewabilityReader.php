<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Domain\Contracts;

interface BookingItemReviewabilityReader
{
    /**
     * Returns true if the booking item is in 'completed' state, owned by the
     * given user (booking.user_id === userId), and not soft-deleted.
     *
     * @param  string  $bookingItemPublicId  CHAR(26) ULID
     * @param  int     $userId               authenticated user id
     */
    public function isReviewable(string $bookingItemPublicId, int $userId): bool;

    /**
     * Returns the internal id and the service_id for a reviewable booking item.
     * Returns null if the item is not reviewable (caller decides 422 vs 403).
     *
     * @return array{booking_item_id: int, service_id: int}|null
     */
    public function resolveReviewableContext(string $bookingItemPublicId, int $userId): ?array;
}
