<?php

declare(strict_types=1);

use App\Modules\Booking\Http\Controllers\Vendor\BookingController;
use App\Modules\Booking\Http\Controllers\Vendor\BookingItemController;
use App\Modules\Booking\Http\Controllers\Vendor\VendorScheduleController;
use App\Modules\Identity\Http\Middleware\SetLocaleMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:vendor', SetLocaleMiddleware::class])
    ->prefix('api/v1/vendor')
    ->group(function (): void {
        Route::get('booking-vendors', [BookingController::class, 'index']);
        Route::get('booking-vendors/{bookingVendorPublicId}', [BookingController::class, 'show']);
        Route::post('booking-vendors/{bookingVendorPublicId}/accept', [BookingController::class, 'accept']);
        Route::post('booking-vendors/{bookingVendorPublicId}/modify', [BookingController::class, 'modify']);
        Route::post('booking-vendors/{bookingVendorPublicId}/preview-modification', [BookingController::class, 'previewModification']);
        Route::get('booking-vendors/{bookingVendorPublicId}/modifications', [BookingController::class, 'modifications']);
        Route::get('booking-vendors/{bookingVendorPublicId}/timeline', [BookingController::class, 'timeline']);
        Route::post('booking-vendors/{bookingVendorPublicId}/reject', [BookingController::class, 'reject']);

        // G7 — schedule + per-type fulfillment transitions.
        Route::get('schedule', VendorScheduleController::class);
        Route::get('booking-items/{bookingItemPublicId}', [BookingItemController::class, 'show']);
        Route::post('booking-items/{bookingItemPublicId}/transition', [BookingItemController::class, 'transition']);
        Route::post('booking-items/{bookingItemPublicId}/condition-photos', [BookingItemController::class, 'conditionPhotos']);
        Route::post('booking-items/{bookingItemPublicId}/report-issue', [BookingItemController::class, 'reportIssue']);
    });
