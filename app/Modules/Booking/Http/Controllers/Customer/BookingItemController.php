<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Controllers\Customer;

use App\Modules\Booking\Application\Actions\AddItemToBookingAction;
use App\Modules\Booking\Application\Actions\RemoveItemFromBookingAction;
use App\Modules\Booking\Domain\Contracts\BookingRepository;
use App\Modules\Booking\Http\Requests\AddBookingItemRequest;
use App\Modules\Booking\Http\Resources\BookingItemResource;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class BookingItemController
{
    public function store(AddBookingItemRequest $request, string $bookingPublicId): JsonResponse
    {
        $booking = app(BookingRepository::class)->findByPublicId($bookingPublicId);
        if ($booking === null) {
            return ApiResponse::error('Booking not found', 404);
        }
        $item = app(AddItemToBookingAction::class)->execute($request->toDTO($booking->id, (int) auth()->id()));

        return ApiResponse::success(new BookingItemResource($item), [], 201);
    }

    public function destroy(string $bookingPublicId, string $itemPublicId): JsonResponse
    {
        $booking = app(BookingRepository::class)->findByPublicId($bookingPublicId);
        if ($booking === null) {
            return ApiResponse::error('Booking not found', 404);
        }
        $result = app(RemoveItemFromBookingAction::class)->execute($booking->id, (int) auth()->id(), $itemPublicId);

        return ApiResponse::success($result);
    }
}
