<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Application\DTOs\SubmitBookingModificationProposalDTO;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\ModificationChangeKind;
use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\VendorModificationProposed;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Shared\Domain\Models\StateTransition;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class SubmitBookingModificationProposalAction
{
    public function __construct(
        private readonly PreventModificationAfterPaymentAction $guard,
        private readonly RecalculateBookingModificationTotalsAction $recalculate,
    ) {}

    public function execute(SubmitBookingModificationProposalDTO $dto): BookingModification
    {
        if ($dto->idempotencyKey !== null) {
            $cached = $this->getCachedIdempotencyResponse($dto);
            if ($cached !== null) {
                return $cached;
            }
        }

        return DB::transaction(function () use ($dto): BookingModification {
            /** @var BookingModification $modification */
            $modification = BookingModification::query()
                ->with('items')
                ->where('id', $dto->bookingModificationId)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if(
                $modification->status !== ModificationStatus::Draft,
                Response::HTTP_CONFLICT,
                'Modification is not in draft status',
            );

            /** @var BookingVendor $bookingVendor */
            $bookingVendor = BookingVendor::query()
                ->where('id', $modification->booking_vendor_id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if(
                $bookingVendor->vendor_profile_id !== $dto->vendorProfileId,
                Response::HTTP_FORBIDDEN,
            );

            $this->guard->execute($bookingVendor);

            abort_if(
                $modification->items->isEmpty(),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Add at least one change before submitting',
            );

            $hasNonRemove = $modification->items->contains(
                fn ($item) => $item->change_kind !== ModificationChangeKind::Remove
            );

            abort_if(
                ! $hasNonRemove,
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'All items are removals — use Reject instead of Modify',
            );

            $hasAnyLocale = collect($dto->vendorExplanation)
                ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                ->isNotEmpty();

            abort_if(
                ! $hasAnyLocale,
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Vendor explanation is required (at least one locale)',
            );

            $this->recalculate->execute($modification);

            $modification = $modification->fresh(['items', 'bookingVendor']);

            $expiresAt = $dto->expiresAt?->toDateTime() ?? now()->addHours(48);

            $modification->update([
                'status' => ModificationStatus::Pending,
                'vendor_explanation' => $dto->vendorExplanation,
                'expires_at' => $expiresAt,
            ]);

            StateTransition::create([
                'transitionable_type' => BookingModification::class,
                'transitionable_id' => $modification->id,
                'from_state' => ModificationStatus::Draft->value,
                'to_state' => ModificationStatus::Pending->value,
                'trigger_kind' => 'vendor',
                'triggered_by' => $dto->proposedByUserId,
                'context' => [
                    'modification_public_id' => $modification->public_id,
                    'price_delta_minor' => $modification->diff_snapshot['totals']['price_delta_minor'] ?? 0,
                ],
            ]);

            $previousSubStatus = $bookingVendor->sub_status->value;
            $bookingVendor->update([
                'sub_status' => VendorSubStatus::Modified,
                'responded_at' => now(),
            ]);

            StateTransition::create([
                'transitionable_type' => BookingVendor::class,
                'transitionable_id' => $bookingVendor->id,
                'from_state' => $previousSubStatus,
                'to_state' => VendorSubStatus::Modified->value,
                'trigger_kind' => 'vendor',
                'triggered_by' => $dto->proposedByUserId,
            ]);

            /** @var Booking $booking */
            $booking = Booking::query()
                ->where('id', $bookingVendor->booking_id)
                ->lockForUpdate()
                ->firstOrFail();

            $previousLifecycle = $booking->lifecycle_status->getValue();

            if ($previousLifecycle !== LifecycleStatus::CustomerReview->value) {
                DB::table('bookings')
                    ->where('id', $booking->id)
                    ->update(['lifecycle_status' => LifecycleStatus::CustomerReview->value]);

                StateTransition::create([
                    'transitionable_type' => Booking::class,
                    'transitionable_id' => $booking->id,
                    'from_state' => $previousLifecycle,
                    'to_state' => LifecycleStatus::CustomerReview->value,
                    'trigger_kind' => 'system',
                    'triggered_by' => $dto->proposedByUserId,
                ]);
            }

            DB::afterCommit(function () use ($modification, $dto): void {
                event(new VendorModificationProposed($modification));
                if ($dto->idempotencyKey !== null) {
                    $this->storeIdempotencyResponse($dto, $modification);
                }
            });

            return $modification;
        });
    }

    private function requestHash(SubmitBookingModificationProposalDTO $dto): string
    {
        return hash('sha256', 'modification.submit|'.$dto->vendorProfileId.'|'.$dto->bookingModificationId);
    }

    private function getCachedIdempotencyResponse(
        SubmitBookingModificationProposalDTO $dto,
    ): ?BookingModification {
        $row = DB::table('idempotency_keys')
            ->where('key', $dto->idempotencyKey)
            ->where('user_id', $dto->proposedByUserId)
            ->where('expires_at', '>', now())
            ->first();

        if ($row === null) {
            return null;
        }

        if (! hash_equals($row->request_hash, $this->requestHash($dto))) {
            abort(Response::HTTP_CONFLICT, 'Idempotency key conflict');
        }

        /** @var array<string,mixed> $body */
        $body = json_decode($row->response_body, true) ?? [];

        return BookingModification::find((int) ($body['booking_modification_id'] ?? 0));
    }

    private function storeIdempotencyResponse(
        SubmitBookingModificationProposalDTO $dto,
        BookingModification $modification,
    ): void {
        DB::table('idempotency_keys')->insertOrIgnore([
            'key' => $dto->idempotencyKey,
            'user_id' => $dto->proposedByUserId,
            'route' => 'modification.submit',
            'request_hash' => $this->requestHash($dto),
            'response_status' => Response::HTTP_OK,
            'response_body' => json_encode(['booking_modification_id' => $modification->id]),
            'expires_at' => now()->addHours(24),
            'created_at' => now(),
        ]);
    }
}
