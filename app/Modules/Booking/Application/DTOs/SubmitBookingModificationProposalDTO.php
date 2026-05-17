<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\DTOs;

use Carbon\CarbonImmutable;

final readonly class SubmitBookingModificationProposalDTO
{
    /**
     * @param  array<string,string>  $vendorExplanation  Translatable JSON, e.g. ['en' => '...', 'ar' => '...'].
     *                                                   At least one locale must be populated.
     */
    public function __construct(
        public int $bookingModificationId,
        public int $vendorProfileId,
        public int $proposedByUserId,
        public array $vendorExplanation,
        public ?CarbonImmutable $expiresAt = null,
        public ?string $idempotencyKey = null,
    ) {}
}
