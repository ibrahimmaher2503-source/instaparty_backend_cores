<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Services;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CancelledState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CompletedState;
use App\Modules\Communication\Domain\Enums\ChatPanelState;
use App\Modules\Communication\Domain\Models\ChatThread;

final class ChatPanelStateResolver
{
    /**
     * Resolve which panel state to render for the authenticated vendor.
     *
     * Precedence (first match wins):
     *  1. No thread yet              → Placeholder
     *  2. Booking lifecycle terminal → Closed
     *  3. Thread frozen by admin     → Frozen
     *  4. Thread locked (lifecycle)  → SystemLocked
     *  5. BookingVendor not found    → Placeholder
     *  6. Sub-status outside window  → SystemLocked
     *  7. Thread open + in window    → Open
     *  8. Default                    → Closed
     */
    public function resolve(?ChatThread $thread, Booking $booking): ChatPanelState
    {
        if ($thread === null) {
            return ChatPanelState::Placeholder;
        }

        if ($booking->lifecycle_status instanceof CompletedState
            || $booking->lifecycle_status instanceof CancelledState) {
            return ChatPanelState::Closed;
        }

        if ($thread->frozen_at !== null) {
            return ChatPanelState::Frozen;
        }

        if ($thread->status === 'locked') {
            return ChatPanelState::SystemLocked;
        }

        $bookingVendor = $thread->bookingVendor;

        if ($bookingVendor === null) {
            return ChatPanelState::Placeholder;
        }

        $inReviewWindow = in_array($bookingVendor->sub_status, [
            VendorSubStatus::Pending,
            VendorSubStatus::Modified,
        ], strict: true);

        if (! $inReviewWindow) {
            return ChatPanelState::SystemLocked;
        }

        if ($thread->status === 'open') {
            return ChatPanelState::Open;
        }

        return ChatPanelState::Closed;
    }
}
