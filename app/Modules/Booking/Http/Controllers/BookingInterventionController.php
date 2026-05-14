<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Controllers;

use App\Modules\Booking\Application\Actions\ForceCancelBookingAction;
use App\Modules\Booking\Application\DTOs\AdminInterventionDTO;
use App\Modules\Booking\Domain\Enums\InterventionType;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Http\Requests\ForceCancelBookingRequest;
use App\Modules\Booking\Http\Resources\BookingAdminInterventionResource;
use App\Modules\Shared\Application\Services\IdempotencyService;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class BookingInterventionController
{
    public function __construct(
        private readonly ForceCancelBookingAction $forceCancelAction,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function forceCancel(ForceCancelBookingRequest $request, string $bookingPublicId): JsonResponse
    {
        Gate::authorize('force_cancel_booking');
        $booking = Booking::where('public_id', $bookingPublicId)->firstOrFail();
        return $this->idempotency->wrap($request, 'admin.bookings.force-cancel', fn () => $this->doForceCancel($booking, $request));
    }

    private function doForceCancel(Booking $booking, ForceCancelBookingRequest $request): JsonResponse
    {
        try {
            $intervention = $this->forceCancelAction->execute(
                $booking,
                new AdminInterventionDTO(
                    bookingId: $booking->id,
                    adminId: auth()->id(),
                    interventionType: InterventionType::ForceCancel,
                    reason: $request->input('reason'),
                ),
            );
        } catch (\DomainException $e) {
            return ApiResponse::error($e->getMessage(), 409);
        }

        return ApiResponse::success(new BookingAdminInterventionResource($intervention->load('booking')));
    }
}
