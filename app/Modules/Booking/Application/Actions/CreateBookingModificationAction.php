<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Application\DTOs\CreateBookingModificationDTO;
use App\Modules\Booking\Domain\Enums\ModificationProposalKind;
use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Shared\Domain\Models\StateTransition;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateBookingModificationAction
{
    public function __construct(
        private readonly PreventModificationAfterPaymentAction $guard,
    ) {}

    public function execute(CreateBookingModificationDTO $dto): BookingModification
    {
        return DB::transaction(function () use ($dto): BookingModification {
            /** @var BookingVendor $bookingVendor */
            $bookingVendor = BookingVendor::query()
                ->where('id', $dto->bookingVendorId)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if(
                $bookingVendor->vendor_profile_id !== $dto->vendorProfileId,
                Response::HTTP_FORBIDDEN
            );

            $this->guard->execute($bookingVendor);

            $existingDraft = BookingModification::query()
                ->where('booking_vendor_id', $bookingVendor->id)
                ->where('status', ModificationStatus::Draft)
                ->lockForUpdate()
                ->first();

            if ($existingDraft !== null) {
                return $existingDraft;
            }

            $pendingExists = BookingModification::query()
                ->where('booking_vendor_id', $bookingVendor->id)
                ->where('status', ModificationStatus::Pending)
                ->exists();

            abort_if(
                $pendingExists,
                Response::HTTP_CONFLICT,
                'A pending modification already awaits customer decision',
            );

            $modification = BookingModification::create([
                'public_id' => (string) Str::ulid(),
                'booking_vendor_id' => $bookingVendor->id,
                'proposed_by' => $dto->proposedByUserId,
                'proposal_kind' => ModificationProposalKind::AddNote,
                'status' => ModificationStatus::Draft,
                'vendor_explanation' => null,
                'diff_snapshot' => [
                    'totals' => [
                        'price_delta_minor' => 0,
                        'currency' => $bookingVendor->subtotal_currency,
                        'item_count' => 0,
                        'by_change_kind' => [],
                    ],
                    'items' => [],
                ],
            ]);

            StateTransition::create([
                'transitionable_type' => BookingModification::class,
                'transitionable_id' => $modification->id,
                'from_state' => null,
                'to_state' => ModificationStatus::Draft->value,
                'trigger_kind' => 'vendor',
                'triggered_by' => $dto->proposedByUserId,
                'context' => ['modification_public_id' => $modification->public_id],
            ]);

            return $modification;
        });
    }
}
