<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Controllers\Customer;

use App\Modules\Booking\Application\Actions\CancelBookingByCustomerAction;
use App\Modules\Booking\Application\Actions\PreviewBookingCancellationAction;
use App\Modules\Booking\Domain\Contracts\BookingRepository;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingCancellationController
{
    public function preview(string $bookingPublicId, PreviewBookingCancellationAction $action): JsonResponse
    {
        return ApiResponse::success($action->execute($this->ownBooking($bookingPublicId))->toArray());
    }

    public function cancel(Request $request, string $bookingPublicId, CancelBookingByCustomerAction $action): JsonResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return ApiResponse::success(
            $action->execute($this->ownBooking($bookingPublicId), (int) auth()->id(), $request->input('reason'))->toArray()
        );
    }

    private function ownBooking(string $publicId): Booking
    {
        $booking = app(BookingRepository::class)->findByPublicId($publicId);
        abort_if($booking === null || $booking->customer_id !== auth()->id(), 404, 'Not found');

        return $booking;
    }
}
