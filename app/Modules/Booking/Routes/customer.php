<?php

declare(strict_types=1);

use App\Modules\Booking\Http\Controllers\Customer\BookingCancellationController;
use App\Modules\Booking\Http\Controllers\Customer\BookingController;
use App\Modules\Booking\Http\Controllers\Customer\BookingItemController;
use App\Modules\Booking\Http\Controllers\Customer\BookingNegotiationController;
use App\Modules\Booking\Http\Controllers\Customer\CheckoutReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'locale', 'role:customer'])
    ->prefix('api/v1/customer')
    ->group(function (): void {
        Route::get('bookings', [BookingController::class, 'index']);
        Route::post('bookings', [BookingController::class, 'store']);
        Route::get('bookings/{bookingPublicId}', [BookingController::class, 'show']);
        Route::post('bookings/{bookingPublicId}/items', [BookingItemController::class, 'store']);
        Route::delete('bookings/{bookingPublicId}/items/{itemPublicId}', [BookingItemController::class, 'destroy']);
        Route::post('bookings/{bookingPublicId}/submit', [BookingNegotiationController::class, 'submit']);
        Route::post('bookings/{bookingPublicId}/checkout-review', CheckoutReviewController::class);
        Route::get('bookings/{bookingPublicId}/cancellation-preview', [BookingCancellationController::class, 'preview']);
        Route::post('bookings/{bookingPublicId}/cancel', [BookingCancellationController::class, 'cancel'])
            ->middleware('idempotency');
        Route::get('bookings/{bookingPublicId}/modifications', [BookingNegotiationController::class, 'listModifications']);
        Route::post('bookings/{bookingPublicId}/modifications/{modificationPublicId}/decide', [BookingNegotiationController::class, 'decideModification']);
    });
