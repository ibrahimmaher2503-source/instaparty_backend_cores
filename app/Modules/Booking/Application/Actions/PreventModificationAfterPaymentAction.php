<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Domain\Enums\FulfillmentStatus;
use App\Modules\Booking\Domain\Exceptions\BookingLockedException;
use App\Modules\Booking\Domain\Exceptions\BookingNotModifiableException;
use App\Modules\Booking\Domain\Exceptions\PaymentAlreadyCapturedException;
use App\Modules\Booking\Domain\Exceptions\ResponseDeadlineExpiredException;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingLock;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CancelledState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CompletedState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\PaidState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\PartialState;
use App\Modules\Payments\Domain\Models\Payment;

class PreventModificationAfterPaymentAction
{
    public function execute(BookingVendor $bookingVendor): void
    {
        if ($bookingVendor->response_deadline !== null
            && $bookingVendor->response_deadline->isPast()) {
            throw new ResponseDeadlineExpiredException($bookingVendor->id);
        }

        /** @var Booking $booking */
        $booking = $bookingVendor->booking()->firstOrFail();

        $hasActiveLock = BookingLock::query()
            ->where('resource_type', Booking::class)
            ->where('resource_id', $booking->id)
            ->whereNull('released_at')
            ->exists();

        if ($hasActiveLock) {
            throw new BookingLockedException($booking->id, Booking::class);
        }

        if ($booking->lifecycle_status instanceof CancelledState) {
            throw new BookingNotModifiableException($booking->id, 'booking_cancelled');
        }

        if ($booking->lifecycle_status instanceof CompletedState) {
            throw new BookingNotModifiableException($booking->id, 'booking_completed');
        }

        if (in_array($booking->fulfillment_status, [
            FulfillmentStatus::InProgress,
            FulfillmentStatus::PartiallyCompleted,
            FulfillmentStatus::Completed,
        ], true)) {
            throw new BookingNotModifiableException($booking->id, 'fulfillment_in_progress');
        }

        if ($booking->payment_status instanceof PaidState
            || $booking->payment_status instanceof PartialState) {
            throw new PaymentAlreadyCapturedException($booking->id);
        }

        $hasCapturedPayment = Payment::query()
            ->where('booking_id', $booking->id)
            ->where('status', 'captured')
            ->exists();

        if ($hasCapturedPayment) {
            throw new PaymentAlreadyCapturedException($booking->id);
        }
    }
}
