<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Domain\Contracts;

interface BookingVendorReviewabilityReader
{
    /**
     * Returns true if EVERY booking_items row under this booking_vendor has
     * item_status='completed', the booking_vendor is owned by the user, and
     * neither the booking_vendor nor any underlying booking_item is soft-deleted.
     */
    public function isReviewable(string $bookingVendorPublicId, int $userId): bool;

    /**
     * Resolves to internal ids needed by the Action.
     *
     * @return array{booking_vendor_id: int, vendor_profile_id: int}|null
     */
    public function resolveReviewableContext(string $bookingVendorPublicId, int $userId): ?array;
}
