<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Application\DTOs\VendorModifyDTO;
use App\Modules\Booking\Application\Services\BookingModificationDiffService;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\ModificationChangeKind;
use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\VendorModificationProposed;
use App\Modules\Booking\Domain\Exceptions\ResponseDeadlineExpiredException;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Booking\Domain\Models\BookingModificationItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CustomerReviewState;
use App\Modules\Shared\Domain\Models\StateTransition;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VendorModifyBookingAction
{
    public function __construct(
        private readonly BookingModificationDiffService $diffService,
    ) {}

    public function execute(VendorModifyDTO $dto): BookingModification
    {
        if ($dto->idempotencyKey !== null) {
            $cached = $this->getCachedIdempotencyResponse($dto);
            if ($cached !== null) {
                return $cached;
            }
        }

        // G6 — preview-token handshake: validate before the transaction,
        // consume (one-time use) only after commit.
        if ($dto->previewToken !== null) {
            $this->assertPreviewTokenValid($dto);
        }

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

            if ($bookingVendor->response_deadline !== null
                && $bookingVendor->response_deadline->isPast()) {
                throw new ResponseDeadlineExpiredException($bookingVendor->id);
            }

            $pendingExists = BookingModification::where('booking_vendor_id', $bookingVendor->id)
                ->where('status', ModificationStatus::Pending)
                ->exists();

            abort_if($pendingExists, Response::HTTP_CONFLICT, 'A pending modification already exists');

            $diffSnapshot = $this->diffService->buildDiffSnapshot($bookingVendor, $dto->changes);

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

            StateTransition::create([
                'transitionable_type' => BookingVendor::class,
                'transitionable_id' => $bookingVendor->id,
                'from_state' => VendorSubStatus::Pending->value,
                'to_state' => VendorSubStatus::Modified->value,
                'trigger_kind' => 'vendor',
                'triggered_by' => $dto->vendorProfileId,
            ]);

            $booking = Booking::query()->where('id', $bookingVendor->booking_id)->lockForUpdate()->firstOrFail();
            $previousStatus = $booking->lifecycle_status->getValue();
            $booking->update(['lifecycle_status' => CustomerReviewState::class]);

            StateTransition::create([
                'transitionable_type' => Booking::class,
                'transitionable_id' => $booking->id,
                'from_state' => $previousStatus,
                'to_state' => LifecycleStatus::CustomerReview->value,
                'trigger_kind' => 'system',
            ]);

            $modification->load('bookingVendor');

            DB::afterCommit(function () use ($modification, $dto): void {
                event(new VendorModificationProposed($modification));
                if ($dto->idempotencyKey !== null) {
                    $this->storeIdempotencyResponse($dto, $modification);
                }
                if ($dto->previewToken !== null) {
                    // One-time use — consume only after a successful commit.
                    Cache::forget(
                        PreviewBookingModificationAction::cacheKey($dto->vendorProfileId, $dto->previewToken)
                    );
                }
            });

            return $modification;
        });
    }

    private function assertPreviewTokenValid(VendorModifyDTO $dto): void
    {
        $entry = Cache::get(
            PreviewBookingModificationAction::cacheKey($dto->vendorProfileId, $dto->previewToken)
        );

        // Missing or expired token → 410 Gone (client must re-preview).
        abort_if($entry === null, Response::HTTP_GONE, __('booking::booking.errors.preview_token_expired'));

        // Token bound to a different booking-vendor or change set → 409.
        $hash = $this->diffService->changesHash($dto->bookingVendorId, $dto->changes);
        abort_if(
            (int) $entry['booking_vendor_id'] !== $dto->bookingVendorId
                || ! hash_equals($entry['changes_hash'], $hash),
            Response::HTTP_CONFLICT,
            __('booking::booking.errors.preview_token_conflict'),
        );
    }

    private function requestHash(VendorModifyDTO $dto): string
    {
        return hash('sha256', 'vendor.modify|'.$dto->vendorProfileId.'|'.$dto->bookingVendorId);
    }

    private function getCachedIdempotencyResponse(VendorModifyDTO $dto): ?BookingModification
    {
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

    private function storeIdempotencyResponse(VendorModifyDTO $dto, BookingModification $modification): void
    {
        DB::table('idempotency_keys')->insertOrIgnore([
            'key' => $dto->idempotencyKey,
            'user_id' => $dto->proposedByUserId,
            'route' => 'vendor.modify',
            'request_hash' => $this->requestHash($dto),
            'response_status' => Response::HTTP_CREATED,
            'response_body' => json_encode(['booking_modification_id' => $modification->id]),
            'expires_at' => now()->addHours(24),
            'created_at' => now(),
        ]);
    }

}
