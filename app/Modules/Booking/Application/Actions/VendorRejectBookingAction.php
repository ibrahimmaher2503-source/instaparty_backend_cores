<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\BookingCancelled;
use App\Modules\Booking\Domain\Events\VendorRejected;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingStateTransition;
use App\Modules\Booking\Domain\Models\BookingVendor;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class VendorRejectBookingAction
{
    /** @param array<string,string>|null $rejectionReason */
    public function execute(int $bookingVendorId, int $vendorProfileId, ?array $rejectionReason = null): BookingVendor
    {
        return DB::transaction(function () use ($bookingVendorId, $vendorProfileId, $rejectionReason): BookingVendor {
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

            $updateData = [
                'sub_status' => VendorSubStatus::Rejected,
                'responded_at' => now(),
            ];
            if ($rejectionReason !== null) {
                $updateData['rejection_reason'] = $rejectionReason;
            }
            $bookingVendor->update($updateData);

            BookingStateTransition::create([
                'transitionable_type' => BookingVendor::class,
                'transitionable_id' => $bookingVendor->id,
                'from_state' => VendorSubStatus::Pending->value,
                'to_state' => VendorSubStatus::Rejected->value,
                'trigger_kind' => 'vendor',
                'triggered_by' => $vendorProfileId,
            ]);

            $booking = Booking::query()->where('id', $bookingVendor->booking_id)->lockForUpdate()->firstOrFail();

            $allRejected = ! BookingVendor::where('booking_id', $booking->id)
                ->where('sub_status', '!=', VendorSubStatus::Rejected->value)
                ->exists();

            $bookingCancelled = false;
            if ($allRejected) {
                $booking->update([
                    'lifecycle_status' => LifecycleStatus::Cancelled,
                    'cancelled_at' => now(),
                ]);

                BookingStateTransition::create([
                    'transitionable_type' => Booking::class,
                    'transitionable_id' => $booking->id,
                    'from_state' => $booking->getOriginal('lifecycle_status') ?? LifecycleStatus::VendorReview->value,
                    'to_state' => LifecycleStatus::Cancelled->value,
                    'trigger_kind' => 'system',
                ]);

                $bookingCancelled = true;
            } else {
                $booking->update(['lifecycle_status' => LifecycleStatus::CustomerReview]);

                BookingStateTransition::create([
                    'transitionable_type' => Booking::class,
                    'transitionable_id' => $booking->id,
                    'from_state' => LifecycleStatus::VendorReview->value,
                    'to_state' => LifecycleStatus::CustomerReview->value,
                    'trigger_kind' => 'system',
                ]);
            }

            DB::afterCommit(function () use ($bookingVendor, $booking, $bookingCancelled): void {
                event(new VendorRejected($bookingVendor));
                if ($bookingCancelled) {
                    event(new BookingCancelled($booking));
                }
            });

            return $bookingVendor;
        });
    }
}
