<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\DraftState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * 10.8 — discards a draft booking ("clear cart"). Drafts only: anything
 * already submitted goes through the cancellation flow instead. Soft
 * delete (bookings are on the soft-delete list) + audit log.
 */
class DiscardDraftBookingAction
{
    public function execute(Booking $booking, int $customerId): void
    {
        if (! $booking->lifecycle_status instanceof DraftState) {
            throw new UnprocessableEntityHttpException(
                __('booking::booking.cancellation.blocked.not_a_draft')
            );
        }

        DB::transaction(function () use ($booking, $customerId): void {
            DB::table('audit_logs')->insert([
                'public_id' => Str::ulid()->toBase32(),
                'auditable_type' => Booking::class,
                'auditable_id' => $booking->id,
                'user_id' => $customerId,
                'action' => 'customer_discarded_draft_booking',
                'changes' => json_encode(['reference_no' => $booking->reference_no]),
                'created_at' => now(),
            ]);

            $booking->delete();
        });
    }
}
