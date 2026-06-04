<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Application\DTOs\CancellationPreviewDTO;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\ActiveState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CancelledState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CompletedState;
use App\Modules\Payments\Domain\Contracts\RefundPolicyResolver;

/**
 * Computes the per-item refund eligibility for a customer-initiated booking
 * cancellation. Per-product-type policy is resolved through the Payments
 * RefundPolicyResolver contract (rental: 24h window / setup-block, sale:
 * in_preparation block, digital: is_refundable_after_delivery flag).
 *
 * Read-only — no mutation, no transaction.
 */
class PreviewBookingCancellationAction
{
    public function __construct(private readonly RefundPolicyResolver $refundPolicy) {}

    public function execute(Booking $booking): CancellationPreviewDTO
    {
        $booking->loadMissing('vendors.items');

        $lifecycleBlock = match (true) {
            $booking->lifecycle_status instanceof CancelledState => 'already_cancelled',
            $booking->lifecycle_status instanceof CompletedState => 'booking_completed',
            $booking->lifecycle_status instanceof ActiveState => 'booking_active',
            default => null,
        };

        $items = [];
        $refundableMinor = 0;
        $nonRefundableMinor = 0;

        foreach ($booking->vendors as $bookingVendor) {
            foreach ($bookingVendor->items as $item) {
                $policy = $this->refundPolicy->policyFor(
                    $item->product_type,
                    (string) $item->item_status,
                    $booking->event_starts_at,
                    (int) $item->service_id,
                );

                $lineTotal = (int) $item->line_total_minor;
                $policy->allowed ? $refundableMinor += $lineTotal : $nonRefundableMinor += $lineTotal;

                $items[] = [
                    'item_public_id' => $item->public_id,
                    'name' => $item->name_snapshot,
                    'product_type' => $item->product_type->value,
                    'refundable' => $policy->allowed,
                    'reason_code' => $policy->reasonCode,
                    'reason_message' => __(
                        str_starts_with($policy->reasonMessageKey, 'payments::')
                            ? $policy->reasonMessageKey
                            : 'payments::'.$policy->reasonMessageKey
                    ),
                    'line_total_minor' => $lineTotal,
                ];
            }
        }

        $amountPaid = (int) ($booking->amount_paid_minor ?? 0);

        // Gateway constraint: only full-payment refunds are supported in
        // Phase 1 (PartialRefundUnsupportedException). Mixed refundability on
        // a paid booking therefore routes to manual payments-ops review.
        $requiresManualReview = $amountPaid > 0 && $nonRefundableMinor > 0 && $refundableMinor > 0;
        $estimatedRefund = ($amountPaid > 0 && $nonRefundableMinor === 0) ? $amountPaid : 0;

        return new CancellationPreviewDTO(
            cancellable: $lifecycleBlock === null,
            cancellableReasonCode: $lifecycleBlock ?? 'cancellable',
            items: $items,
            refundableMinor: $refundableMinor,
            nonRefundableMinor: $nonRefundableMinor,
            amountPaidMinor: $amountPaid,
            estimatedRefundMinor: $estimatedRefund,
            currency: (string) ($booking->total_currency ?? 'EGP'),
            requiresManualRefundReview: $requiresManualReview,
        );
    }
}
