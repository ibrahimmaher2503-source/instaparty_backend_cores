<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Application\DTOs\VendorAcceptDTO;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\BookingConfirmed;
use App\Modules\Booking\Domain\Events\VendorAccepted;
use App\Modules\Booking\Domain\Exceptions\ResponseDeadlineExpiredException;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\ConfirmedState;
use App\Modules\Shared\Domain\Models\StateTransition;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class VendorAcceptBookingAction
{
    public function execute(VendorAcceptDTO $dto): BookingVendor
    {
        if ($dto->idempotencyKey !== null) {
            $cached = $this->getCachedIdempotencyResponse($dto);
            if ($cached !== null) {
                return $cached;
            }
        }

        return DB::transaction(function () use ($dto): BookingVendor {
            /** @var BookingVendor $bookingVendor */
            $bookingVendor = BookingVendor::query()->where('id', $dto->bookingVendorId)->lockForUpdate()->firstOrFail();

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

            $bookingVendor->update([
                'sub_status' => VendorSubStatus::Accepted,
                'responded_at' => now(),
            ]);

            StateTransition::create([
                'transitionable_type' => BookingVendor::class,
                'transitionable_id' => $bookingVendor->id,
                'from_state' => VendorSubStatus::Pending->value,
                'to_state' => VendorSubStatus::Accepted->value,
                'trigger_kind' => 'vendor',
                'triggered_by' => $dto->vendorProfileId,
            ]);

            /** @var Booking $booking */
            $booking = Booking::query()->where('id', $bookingVendor->booking_id)->lockForUpdate()->firstOrFail();

            $allAccepted = ! BookingVendor::where('booking_id', $booking->id)
                ->where('sub_status', '!=', VendorSubStatus::Accepted->value)
                ->exists();

            $bookingConfirmed = false;
            if ($allAccepted) {
                $booking->update([
                    'lifecycle_status' => ConfirmedState::class,
                    'confirmed_at' => now(),
                ]);

                StateTransition::create([
                    'transitionable_type' => Booking::class,
                    'transitionable_id' => $booking->id,
                    'from_state' => LifecycleStatus::VendorReview->value,
                    'to_state' => LifecycleStatus::Confirmed->value,
                    'trigger_kind' => 'system',
                ]);

                $bookingConfirmed = true;
            }

            DB::afterCommit(function () use ($bookingVendor, $booking, $bookingConfirmed, $dto): void {
                event(new VendorAccepted($bookingVendor));
                if ($bookingConfirmed) {
                    event(new BookingConfirmed($booking));
                }
                if ($dto->idempotencyKey !== null) {
                    $this->storeIdempotencyResponse($dto, $bookingVendor);
                }
            });

            return $bookingVendor;
        });
    }

    private function requestHash(VendorAcceptDTO $dto): string
    {
        return hash('sha256', 'vendor.accept|'.$dto->vendorProfileId.'|'.$dto->bookingVendorId);
    }

    private function getCachedIdempotencyResponse(VendorAcceptDTO $dto): ?BookingVendor
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

        return BookingVendor::find((int) ($body['booking_vendor_id'] ?? 0));
    }

    private function storeIdempotencyResponse(VendorAcceptDTO $dto, BookingVendor $bookingVendor): void
    {
        DB::table('idempotency_keys')->insertOrIgnore([
            'key' => $dto->idempotencyKey,
            'user_id' => $dto->proposedByUserId,
            'route' => 'vendor.accept',
            'request_hash' => $this->requestHash($dto),
            'response_status' => Response::HTTP_OK,
            'response_body' => json_encode(['booking_vendor_id' => $bookingVendor->id]),
            'expires_at' => now()->addHours(24),
            'created_at' => now(),
        ]);
    }
}
