<?php

declare(strict_types=1);

use App\Modules\Payments\Http\Controllers\Customer\InitiatePaymentController;
use App\Modules\Payments\Http\Controllers\Customer\ShowPaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'idempotency'])->group(function (): void {
    Route::post('/api/v1/customer/bookings/{bookingPublicId}/payments', InitiatePaymentController::class);
});

Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::get('/api/v1/customer/payments/{paymentPublicId}', ShowPaymentController::class);
});
