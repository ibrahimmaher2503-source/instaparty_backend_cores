<?php

declare(strict_types=1);

use App\Modules\Loyalty\Http\Controllers\Customer\LoyaltyBalanceController;
use App\Modules\Loyalty\Http\Controllers\Customer\LoyaltyBalancesController;
use App\Modules\Loyalty\Http\Controllers\Customer\LoyaltyProgramInfoController;
use App\Modules\Loyalty\Http\Controllers\Customer\LoyaltyRedemptionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])
    ->prefix('customer/loyalty')
    ->group(function () {
        Route::get('programs/{vendorPublicId}/balance', [LoyaltyBalanceController::class, 'show']);
        Route::get('programs/{vendorPublicId}/history', [LoyaltyProgramInfoController::class, 'history']);
        Route::get('programs/{vendorPublicId}/rules', [LoyaltyProgramInfoController::class, 'rules']);
        Route::get('balances', [LoyaltyBalancesController::class, 'index']);
    });

Route::middleware(['auth:sanctum', 'idempotency', 'throttle:loyalty-redemption'])
    ->prefix('customer/bookings')
    ->group(function () {
        Route::post('{bookingPublicId}/redemptions', [LoyaltyRedemptionController::class, 'store']);
        Route::delete('{bookingPublicId}/redemptions/{redemptionPublicId}', [LoyaltyRedemptionController::class, 'destroy'])
            ->withoutMiddleware(['idempotency']);
    });
