<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\Contracts;

final readonly class BookingDraftData
{
    public function __construct(
        public int $bookingId,
        public int $vendorProfileId,
        public int $subtotalMinor,
        public string $currency,
        public int $customerId,
    ) {}
}

interface BookingDraftReader
{
    /**
     * Returns draft booking data needed for redemption validation.
     * Throws if booking is not in draft/pending state.
     */
    public function draftFor(int $bookingId): BookingDraftData;

    public function draftForPublicId(string $bookingPublicId): BookingDraftData;
}
