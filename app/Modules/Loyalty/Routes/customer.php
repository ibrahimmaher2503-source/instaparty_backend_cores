<?php

declare(strict_types=1);

use App\Modules\Loyalty\Http\Controllers\Customer\LoyaltyBalanceController;
use App\Modules\Loyalty\Http\Controllers\Customer\LoyaltyRedemptionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])
    ->prefix('customer/loyalty')
    ->group(function () {
        Route::get('programs/{vendorPublicId}/balance', [LoyaltyBalanceController::class, 'show']);
    });

Route::middleware(['auth:sanctum', 'idempotency'])
    ->prefix('customer/bookings')
    ->group(function () {
        Route::post('{bookingPublicId}/redemptions', [LoyaltyRedemptionController::class, 'store']);
        Route::delete('{bookingPublicId}/redemptions/{redemptionPublicId}', [LoyaltyRedemptionController::class, 'destroy']);
    });
