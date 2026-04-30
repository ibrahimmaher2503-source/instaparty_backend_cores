<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\BookingConfirmed;
use App\Modules\Booking\Domain\Events\VendorAccepted;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingStateTransition;
use App\Modules\Booking\Domain\Models\BookingVendor;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class VendorAcceptBookingAction
{
    public function execute(int $bookingVendorId, int $vendorProfileId): BookingVendor
    {
        return DB::transaction(function () use ($bookingVendorId, $vendorProfileId): BookingVendor {
            /** @var BookingVendor $bookingVendor */
            $bookingVendor = BookingVendor::query()->where('id', $bookingVendorId)->lockForUpdate()->firstOrFail();

            abort_if(
                $bookingVendor->vendor_profile_id !== $vendorProfileId,
                Response::HTTP_FORBIDDEN
            );

            abort_if(
                $bookingVendor->sub_status !== VendorSubStatus::Pending,
                Response::HTTP_CONFLICT,
                'Booking vendor is not in pending status'
            );

            $bookingVendor->update([
                'sub_status'   => VendorSubStatus::Accepted,
                'responded_at' => now(),
            ]);

            BookingStateTransition::create([
                'transitionable_type' => BookingVendor::class,
                'transitionable_id'   => $bookingVendor->id,
                'from_state'          => VendorSubStatus::Pending->value,
                'to_state'            => VendorSubStatus::Accepted->value,
                'trigger_kind'        => 'vendor',
                'triggered_by'        => $vendorProfileId,
            ]);

            /** @var Booking $booking */
            $booking = Booking::query()->where('id', $bookingVendor->booking_id)->lockForUpdate()->firstOrFail();

            $allAccepted = ! BookingVendor::where('booking_id', $booking->id)
                ->where('sub_status', '!=', VendorSubStatus::Accepted->value)
                ->exists();

            $bookingConfirmed = false;
            if ($allAccepted) {
                $booking->update([
                    'lifecycle_status' => LifecycleStatus::Confirmed,
                    'confirmed_at'     => now(),
                ]);

                BookingStateTransition::create([
                    'transitionable_type' => Booking::class,
                    'transitionable_id'   => $booking->id,
                    'from_state'          => LifecycleStatus::VendorReview->value,
                    'to_state'            => LifecycleStatus::Confirmed->value,
                    'trigger_kind'        => 'system',
                ]);

                $bookingConfirmed = true;
            }

            DB::afterCommit(function () use ($bookingVendor, $booking, $bookingConfirmed): void {
                event(new VendorAccepted($bookingVendor));
                if ($bookingConfirmed) {
                    event(new BookingConfirmed($booking));
                }
            });

            return $bookingVendor;
        });
    }
}
