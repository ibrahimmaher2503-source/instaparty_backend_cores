<?php

declare(strict_types=1);

use App\Modules\Booking\Http\Controllers\BookingInterventionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:super_admin|booking_manager'])
    ->prefix('api/v1/admin')
    ->group(function (): void {
        Route::post('bookings/{bookingPublicId}/force-cancel', [BookingInterventionController::class, 'forceCancel']);
    });
