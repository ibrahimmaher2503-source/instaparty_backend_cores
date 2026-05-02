<?php

declare(strict_types=1);

use App\Modules\Payments\Http\Controllers\Admin\InitiateRefundController;
use App\Modules\Payments\Http\Controllers\Admin\ShowRefundController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'idempotency'])->group(function (): void {
    Route::post('/api/v1/admin/bookings/{bookingPublicId}/refunds', InitiateRefundController::class);
});

Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::get('/api/v1/admin/refunds/{refundPublicId}', ShowRefundController::class);
});
