<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Loyalty;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CustomerReviewState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\DraftState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\SubmittedState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\VendorReviewState;
use App\Modules\Loyalty\Domain\Contracts\BookingDraftData;
use App\Modules\Loyalty\Domain\Contracts\BookingDraftReader;
use DomainException;
use RuntimeException;

final class EloquentBookingDraftReader implements BookingDraftReader
{
    private const DRAFT_STATE_CLASSES = [
        DraftState::class,
        SubmittedState::class,
        VendorReviewState::class,
        CustomerReviewState::class,
    ];

    public function draftFor(int $bookingId): BookingDraftData
    {
        $booking = Booking::query()->with('vendors:id,booking_id,vendor_profile_id')->find($bookingId);

        if ($booking === null) {
            throw new RuntimeException("Booking {$bookingId} not found.");
        }

        return $this->toDraftData($booking);
    }

    public function draftForPublicId(string $bookingPublicId): BookingDraftData
    {
        $booking = Booking::query()
            ->with('vendors:id,booking_id,vendor_profile_id')
            ->where('public_id', $bookingPublicId)
            ->first();

        if ($booking === null) {
            throw new RuntimeException("Booking with public id {$bookingPublicId} not found.");
        }

        return $this->toDraftData($booking);
    }

    private function toDraftData(Booking $booking): BookingDraftData
    {
        if (! in_array($booking->lifecycle_status::class, self::DRAFT_STATE_CLASSES, true)) {
            throw new DomainException('booking.not_draft');
        }

        $vendors = $booking->vendors;
        if ($vendors->isEmpty()) {
            throw new DomainException('loyalty.no_vendor_on_booking');
        }

        // Phase 1: loyalty redemption is per-vendor program. Multi-vendor bookings
        // would require a redemption scoped to a BookingVendor — not supported yet.
        if ($vendors->count() > 1) {
            throw new DomainException('loyalty.multi_vendor_redemption_unsupported');
        }

        $vendor = $vendors->first();

        return new BookingDraftData(
            bookingId: (int) $booking->id,
            vendorProfileId: (int) $vendor->vendor_profile_id,
            subtotalMinor: (int) $booking->subtotal_minor,
            currency: (string) ($booking->subtotal_currency ?: 'EGP'),
            customerId: (int) $booking->customer_id,
        );
    }
}
