<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Services;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\DraftState;

/**
 * 11.1 — pre-submit checkout review. Read-only validation summary for the
 * checkout screen: the client shows blockers BEFORE calling submit (which
 * re-validates authoritatively). Pricing is whatever the draft already
 * carries — totals are never recomputed here.
 */
final class CheckoutReviewService
{
    public function __construct(private readonly CoverageMinimumStatusService $coverageMinimum) {}

    /** @return array<string, mixed> */
    public function review(Booking $booking): array
    {
        $booking->loadMissing(['vendors.items', 'address', 'vendors']);

        $itemsCount = $booking->vendors->sum(fn ($v) => $v->items->count());

        $minOrderEntries = $this->coverageMinimum->statusFor($booking);
        $minOrderOk = collect($minOrderEntries)->every(
            fn (array $entry): bool => $entry['status'] === null || ($entry['status']->meetsMinimum ?? true)
        );

        $checks = [
            'is_draft' => $booking->lifecycle_status instanceof DraftState,
            'has_items' => $itemsCount > 0,
            'has_address' => $booking->address !== null,
            'event_in_future' => $booking->event_starts_at !== null && $booking->event_starts_at->isFuture(),
            'min_order_ok' => $minOrderOk,
        ];

        return [
            'ready_to_submit' => ! in_array(false, $checks, true),
            'checks' => $checks,
            'items_count' => $itemsCount,
            'subtotal_minor' => (int) ($booking->subtotal_minor ?? 0),
            'delivery_total_minor' => (int) ($booking->delivery_total_minor ?? 0),
            'discount_total_minor' => (int) ($booking->discount_total_minor ?? 0),
            'loyalty_redeemed_minor' => (int) ($booking->loyalty_redeemed_minor ?? 0),
            'total_minor' => (int) ($booking->total_minor ?? 0),
            'currency' => (string) ($booking->total_currency ?? 'EGP'),
        ];
    }
}
