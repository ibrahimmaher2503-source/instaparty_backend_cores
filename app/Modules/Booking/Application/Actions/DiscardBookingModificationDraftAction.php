<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Booking\Domain\Models\BookingModification;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DiscardBookingModificationDraftAction
{
    public function execute(BookingModification $modification, int $userId, int $vendorProfileId): void
    {
        DB::transaction(function () use ($modification, $userId, $vendorProfileId): void {
            $modification->refresh();

            abort_if(
                $modification->status !== ModificationStatus::Draft,
                Response::HTTP_CONFLICT,
                'Only draft modifications may be discarded',
            );

            $bookingVendor = $modification->bookingVendor()->firstOrFail();

            abort_if(
                $bookingVendor->vendor_profile_id !== $vendorProfileId,
                Response::HTTP_FORBIDDEN,
            );

            $changeCount = $modification->items()->count();
            $modificationPublicId = $modification->public_id;

            DB::table('audit_logs')->insert([
                'public_id' => Str::ulid()->toBase32(),
                'auditable_type' => BookingModification::class,
                'auditable_id' => $modification->id,
                'user_id' => $userId,
                'action' => 'modification.draft_discarded',
                'changes' => json_encode([
                    'modification_public_id' => $modificationPublicId,
                    'booking_vendor_id' => $bookingVendor->id,
                    'change_count' => $changeCount,
                ]),
                'created_at' => now(),
            ]);

            $modification->items()->delete();
            $modification->delete();
        });
    }
}
