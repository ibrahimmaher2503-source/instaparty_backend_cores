<?php

declare(strict_types=1);

use App\Modules\Booking\Http\Controllers\Customer\BookingController;
use App\Modules\Booking\Http\Controllers\Customer\BookingItemController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:customer'])
    ->prefix('api/v1/customer')
    ->group(function (): void {
        Route::post('bookings', [BookingController::class, 'store']);
        Route::get('bookings/{bookingPublicId}', [BookingController::class, 'show']);
        Route::post('bookings/{bookingPublicId}/items', [BookingItemController::class, 'store']);
        Route::delete('bookings/{bookingPublicId}/items/{itemPublicId}', [BookingItemController::class, 'destroy']);
    });
