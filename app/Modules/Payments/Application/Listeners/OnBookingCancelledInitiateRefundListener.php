<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Listeners;

use App\Modules\Booking\Domain\Events\BookingCancelled;
use App\Modules\Payments\Application\Actions\InitiateRefundAction;
use App\Modules\Payments\Application\DTOs\InitiateRefundDto;
use App\Modules\Payments\Domain\Contracts\RefundPolicyResolver;
use App\Modules\Payments\Domain\Enums\RefundReasonCode;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\States\PaymentStatus\CapturedState;

/**
 * Customer cancellation refund hook (audit endpoint 12.4).
 *
 * Phase 1 gateway constraint: full-payment refunds only. A captured payment
 * is auto-refunded only when EVERY booking item is policy-refundable
 * (per-product-type via RefundPolicyResolver). Mixed refundability is left
 * for payments-ops manual review (audit row written by the cancel action).
 */
class OnBookingCancelledInitiateRefundListener
{
    public function __construct(
        private readonly RefundPolicyResolver $refundPolicy,
        private readonly InitiateRefundAction $initiateRefund,
    ) {}

    public function handle(BookingCancelled $event): void
    {
        $booking = $event->booking;

        if ((int) ($booking->amount_paid_minor ?? 0) <= 0) {
            return;
        }

        $booking->loadMissing('vendors.items');

        foreach ($booking->vendors as $bookingVendor) {
            foreach ($bookingVendor->items as $item) {
                $policy = $this->refundPolicy->policyFor(
                    $item->product_type,
                    (string) $item->item_status,
                    $booking->event_starts_at,
                    (int) $item->service_id,
                );

                if (! $policy->allowed) {
                    return; // mixed/none refundable → manual review path
                }
            }
        }

        $payment = Payment::query()
            ->where('booking_id', $booking->id)
            ->whereState('status', CapturedState::class)
            ->latest('id')
            ->first();

        if ($payment === null) {
            return; // amount_paid set but no captured payment row — payments-ops reconciles.
        }

        $this->initiateRefund->execute(new InitiateRefundDto(
            paymentId: (int) $payment->id,
            bookingId: (int) $booking->id,
            initiatedBy: (int) ($booking->cancelled_by ?? $booking->customer_id),
            reasonCode: RefundReasonCode::CustomerRequest,
            reasonNotes: [
                'en' => 'Customer cancelled the booking.',
                'ar' => 'قام العميل بإلغاء الحجز.',
            ],
        ));
    }
}
