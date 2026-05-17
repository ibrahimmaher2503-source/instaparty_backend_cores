<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Shared\Domain\Models\StateTransition;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class MarkBookingItemStateAction
{
    /** @param class-string $toStateClass */
    public function execute(BookingItem $item, VendorProfile $vendorProfile, string $toStateClass): BookingItem
    {
        return DB::transaction(function () use ($item, $vendorProfile, $toStateClass): BookingItem {
            $item->load('bookingVendor');

            abort_if(
                $item->bookingVendor->vendor_profile_id !== $vendorProfile->id,
                Response::HTTP_FORBIDDEN
            );

            $fromState = $item->item_status;
            $currentStateInstance = $item->resolveItemState();

            abort_unless(
                $currentStateInstance->canTransitionTo($toStateClass),
                Response::HTTP_CONFLICT,
                "Cannot transition from {$fromState} to {$toStateClass}"
            );

            $toStateName = $toStateClass::$name;
            $item->update(['item_status' => $toStateName]);

            StateTransition::create([
                'transitionable_type' => BookingItem::class,
                'transitionable_id' => $item->id,
                'from_state' => $fromState,
                'to_state' => $toStateName,
                'trigger_kind' => 'vendor',
                'triggered_by' => $vendorProfile->id,
            ]);

            return $item->refresh();
        });
    }
}
