<?php

declare(strict_types=1);

namespace App\Modules\Booking\Http\Controllers\Customer;

use App\Modules\Booking\Application\Services\CheckoutReviewService;
use App\Modules\Booking\Domain\Contracts\BookingRepository;
use App\Modules\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * @group Customer - Bookings
 */
class CheckoutReviewController
{
    public function __invoke(string $bookingPublicId, CheckoutReviewService $service): JsonResponse
    {
        $booking = app(BookingRepository::class)->findByPublicId($bookingPublicId);
        abort_if($booking === null || $booking->customer_id !== auth()->id(), 404, 'Not found');

        return ApiResponse::success($service->review($booking));
    }
}
