<?php

declare(strict_types=1);

use App\Modules\Booking\Http\Controllers\Customer\BookingController;
use App\Modules\Booking\Http\Controllers\Customer\BookingItemController;
use App\Modules\Booking\Http\Controllers\Customer\BookingNegotiationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:customer'])
    ->prefix('api/v1/customer')
    ->group(function (): void {
        Route::get('bookings', [BookingController::class, 'index']);
        Route::post('bookings', [BookingController::class, 'store']);
        Route::get('bookings/{bookingPublicId}', [BookingController::class, 'show']);
        Route::post('bookings/{bookingPublicId}/items', [BookingItemController::class, 'store']);
        Route::delete('bookings/{bookingPublicId}/items/{itemPublicId}', [BookingItemController::class, 'destroy']);
        Route::post('bookings/{bookingPublicId}/submit', [BookingNegotiationController::class, 'submit']);
        Route::get('bookings/{bookingPublicId}/modifications', [BookingNegotiationController::class, 'listModifications']);
        Route::post('bookings/{bookingPublicId}/modifications/{modificationPublicId}/decide', [BookingNegotiationController::class, 'decideModification']);
    });
