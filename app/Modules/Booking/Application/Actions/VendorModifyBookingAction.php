<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Application\DTOs\VendorModifyDTO;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\ModificationChangeKind;
use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\VendorModificationProposed;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Booking\Domain\Models\BookingModificationItem;
use App\Modules\Booking\Domain\Models\BookingStateTransition;
use App\Modules\Booking\Domain\Models\BookingVendor;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VendorModifyBookingAction
{
    public function execute(VendorModifyDTO $dto): BookingModification
    {
        return DB::transaction(function () use ($dto): BookingModification {
            /** @var BookingVendor $bookingVendor */
            $bookingVendor = BookingVendor::query()->with('items')->where('id', $dto->bookingVendorId)->lockForUpdate()->firstOrFail();

            abort_if(
                $bookingVendor->vendor_profile_id !== $dto->vendorProfileId,
                Response::HTTP_FORBIDDEN
            );

            abort_if(
                $bookingVendor->sub_status !== VendorSubStatus::Pending,
                Response::HTTP_CONFLICT,
                'Booking vendor is not in pending status'
            );

            $pendingExists = BookingModification::where('booking_vendor_id', $bookingVendor->id)
                ->where('status', ModificationStatus::Pending)
                ->exists();

            abort_if($pendingExists, Response::HTTP_CONFLICT, 'A pending modification already exists');

            $diffSnapshot = $this->buildDiffSnapshot($bookingVendor, $dto);

            /** @var BookingModification $modification */
            $modification = BookingModification::create([
                'public_id' => (string) Str::ulid(),
                'booking_vendor_id' => $bookingVendor->id,
                'proposed_by' => $dto->proposedByUserId,
                'proposal_kind' => $dto->proposalKind,
                'status' => ModificationStatus::Pending,
                'vendor_explanation' => $dto->vendorExplanation,
                'diff_snapshot' => $diffSnapshot,
            ]);

            foreach ($dto->changes as $change) {
                $targetItemId = null;
                if (isset($change['target_item_public_id'])) {
                    $item = BookingItem::where('public_id', $change['target_item_public_id'])
                        ->where('booking_vendor_id', $bookingVendor->id)
                        ->first();
                    $targetItemId = $item?->id;
                }

                BookingModificationItem::create([
                    'booking_modification_id' => $modification->id,
                    'target_booking_item_id' => $targetItemId,
                    'change_kind' => ModificationChangeKind::from($change['change_kind']),
                    'payload' => $change['payload'],
                ]);
            }

            $bookingVendor->update([
                'sub_status' => VendorSubStatus::Modified,
                'responded_at' => now(),
            ]);

            BookingStateTransition::create([
                'transitionable_type' => BookingVendor::class,
                'transitionable_id' => $bookingVendor->id,
                'from_state' => VendorSubStatus::Pending->value,
                'to_state' => VendorSubStatus::Modified->value,
                'trigger_kind' => 'vendor',
                'triggered_by' => $dto->vendorProfileId,
            ]);

            $booking = Booking::query()->where('id', $bookingVendor->booking_id)->lockForUpdate()->firstOrFail();
            $previousStatus = $booking->lifecycle_status->value;
            $booking->update(['lifecycle_status' => LifecycleStatus::CustomerReview]);

            BookingStateTransition::create([
                'transitionable_type' => Booking::class,
                'transitionable_id' => $booking->id,
                'from_state' => $previousStatus,
                'to_state' => LifecycleStatus::CustomerReview->value,
                'trigger_kind' => 'system',
            ]);

            $modification->load('bookingVendor');

            DB::afterCommit(fn () => event(new VendorModificationProposed($modification)));

            return $modification;
        });
    }

    /** @return array<string,mixed> */
    private function buildDiffSnapshot(BookingVendor $bookingVendor, VendorModifyDTO $dto): array
    {
        $currentItems = $bookingVendor->items->map(fn (BookingItem $item) => [
            'public_id' => $item->public_id,
            'unit_price_minor' => $item->unit_price_minor,
            'unit_price_currency' => $item->unit_price_currency,
            'quantity' => $item->quantity,
            'effective_starts_at' => $item->effective_starts_at?->toIso8601String(),
            'effective_ends_at' => $item->effective_ends_at?->toIso8601String(),
        ])->keyBy('public_id')->all();

        $afterItems = $currentItems;
        $subtotalBefore = $bookingVendor->subtotal_minor;
        $subtotalAfter = $subtotalBefore;

        foreach ($dto->changes as $change) {
            $kind = $change['change_kind'];

            if ($kind === ModificationChangeKind::Update->value && isset($change['target_item_public_id'])) {
                $pubId = $change['target_item_public_id'];
                if (isset($afterItems[$pubId])) {
                    $payload = $change['payload'];
                    $oldPrice = (int) $afterItems[$pubId]['unit_price_minor'];
                    $newPrice = isset($payload['unit_price_minor']) ? (int) $payload['unit_price_minor'] : $oldPrice;
                    $qty = (int) $afterItems[$pubId]['quantity'];
                    $afterItems[$pubId] = array_merge($afterItems[$pubId], $payload);
                    $subtotalAfter += ($newPrice - $oldPrice) * $qty;
                }
            } elseif ($kind === ModificationChangeKind::Add->value) {
                $payload = $change['payload'];
                $newPubId = (string) Str::ulid();
                $price = (int) ($payload['unit_price_minor'] ?? 0);
                $qty = (int) ($payload['quantity'] ?? 1);
                $afterItems[$newPubId] = array_merge(['public_id' => $newPubId], $payload);
                $subtotalAfter += $price * $qty;
            } elseif ($kind === ModificationChangeKind::Remove->value && isset($change['target_item_public_id'])) {
                $pubId = $change['target_item_public_id'];
                if (isset($afterItems[$pubId])) {
                    $price = (int) $afterItems[$pubId]['unit_price_minor'];
                    $qty = (int) $afterItems[$pubId]['quantity'];
                    $subtotalAfter -= $price * $qty;
                    unset($afterItems[$pubId]);
                }
            }
        }

        return [
            'before' => [
                'items' => array_values($currentItems),
                'subtotal_minor' => $subtotalBefore,
            ],
            'after' => [
                'items' => array_values($afterItems),
                'subtotal_minor' => $subtotalAfter,
            ],
        ];
    }
}
