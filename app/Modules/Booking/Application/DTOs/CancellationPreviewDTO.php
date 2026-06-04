<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\DTOs;

/**
 * Read model for GET /customer/bookings/{id}/cancellation-preview.
 * Money values are integer minor units (CLAUDE.md §6).
 *
 * @phpstan-type PreviewItem array{
 *   item_public_id: string,
 *   name: array<string,string>,
 *   product_type: string,
 *   refundable: bool,
 *   reason_code: string,
 *   reason_message: string,
 *   line_total_minor: int
 * }
 */
final readonly class CancellationPreviewDTO
{
    /** @param list<array<string,mixed>> $items */
    public function __construct(
        public bool $cancellable,
        public string $cancellableReasonCode,
        public array $items,
        public int $refundableMinor,
        public int $nonRefundableMinor,
        public int $amountPaidMinor,
        public int $estimatedRefundMinor,
        public string $currency,
        public bool $requiresManualRefundReview,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'cancellable' => $this->cancellable,
            'reason_code' => $this->cancellableReasonCode,
            'items' => $this->items,
            'refundable_minor' => $this->refundableMinor,
            'non_refundable_minor' => $this->nonRefundableMinor,
            'amount_paid_minor' => $this->amountPaidMinor,
            'estimated_refund_minor' => $this->estimatedRefundMinor,
            'currency' => $this->currency,
            'requires_manual_refund_review' => $this->requiresManualRefundReview,
        ];
    }
}
