<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Controllers\Customer;

use App\Modules\Booking\Application\Actions\CustomerConfirmModifiedBookingAction;
use App\Modules\Booking\Application\Actions\SubmitBookingAction;
use App\Modules\Booking\Application\DTOs\CustomerModificationDecisionDTO;
use App\Modules\Booking\Application\DTOs\SubmitBookingDTO;
use App\Modules\Booking\Domain\Contracts\BookingRepository;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Booking\Http\Requests\CustomerModificationDecisionRequest;
use App\Modules\Booking\Http\Requests\SubmitBookingRequest;
use App\Modules\Booking\Http\Resources\BookingModificationResource;
use App\Modules\Booking\Http\Resources\BookingResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class BookingNegotiationController
{
    public function submit(SubmitBookingRequest $request, string $bookingPublicId): JsonResponse
    {
        $booking = app(BookingRepository::class)->findByPublicId($bookingPublicId);
        abort_if($booking === null, 404);

        $result = app(SubmitBookingAction::class)->execute(
            new SubmitBookingDTO((int) $booking->id, (int) auth()->id(), $request->idempotencyKey())
        );

        return ApiResponse::success(new BookingResource($result->load('vendors')));
    }

    public function listModifications(string $bookingPublicId): JsonResponse
    {
        $booking = app(BookingRepository::class)->findByPublicId($bookingPublicId);
        abort_if($booking === null || $booking->customer_id !== (int) auth()->id(), 404);

        $modifications = BookingModification::whereHas(
            'bookingVendor',
            fn ($q) => $q->where('booking_id', $booking->id)
        )->latest()->get();

        return ApiResponse::success(BookingModificationResource::collection($modifications));
    }

    public function decideModification(
        CustomerModificationDecisionRequest $request,
        string $bookingPublicId,
        string $modificationPublicId,
    ): JsonResponse {
        $booking = app(BookingRepository::class)->findByPublicId($bookingPublicId);
        abort_if($booking === null, 404);

        $result = app(CustomerConfirmModifiedBookingAction::class)->execute(
            new CustomerModificationDecisionDTO(
                (int) $booking->id,
                (int) auth()->id(),
                $modificationPublicId,
                $request->validated('decision'),
                $request->idempotencyKey(),
            )
        );

        return ApiResponse::success(new BookingResource($result));
    }
}
