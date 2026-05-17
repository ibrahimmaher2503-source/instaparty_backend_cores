<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\DTOs;

use App\Modules\Booking\Domain\Enums\ModificationChangeKind;
use App\Modules\Booking\Domain\Enums\ModificationProposalKind;

/**
 * Per-item modification draft DTO. The caller supplies flat business fields;
 * AddBookingModificationItemAction resolves the original snapshot, computes
 * deltas via match(ProductType), and persists payload with both flat keys
 * (consumed by CustomerConfirmModifiedBookingAction) and metadata.
 */
final readonly class AddBookingModificationItemDTO
{
    /**
     * @param  array<string,mixed>  $payload  Proposed business fields. Recognised keys
     *                                        depend on changeType: unit_price_minor, unit_price_currency,
     *                                        quantity, effective_starts_at, effective_ends_at, service_id,
     *                                        product_type, customization_data, vendor_note (translatable JSON).
     */
    public function __construct(
        public int $bookingModificationId,
        public int $vendorProfileId,
        public int $proposedByUserId,
        public ModificationChangeKind $changeKind,
        public ModificationProposalKind $changeType,
        public ?int $targetBookingItemId,
        public array $payload,
    ) {}
}
