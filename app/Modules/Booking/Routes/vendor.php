<?php

declare(strict_types=1);

use App\Modules\Booking\Http\Controllers\Vendor\BookingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:vendor'])
    ->prefix('api/v1/vendor')
    ->group(function (): void {
        Route::get('booking-vendors', [BookingController::class, 'index']);
        Route::get('booking-vendors/{bookingVendorPublicId}', [BookingController::class, 'show']);
        Route::post('booking-vendors/{bookingVendorPublicId}/accept', [BookingController::class, 'accept']);
        Route::post('booking-vendors/{bookingVendorPublicId}/modify', [BookingController::class, 'modify']);
        Route::get('booking-vendors/{bookingVendorPublicId}/modifications', [BookingController::class, 'modifications']);
        Route::post('booking-vendors/{bookingVendorPublicId}/reject', [BookingController::class, 'reject']);
    });
