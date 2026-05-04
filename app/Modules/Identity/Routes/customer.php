<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\CustomerAddressController;
use App\Modules\Identity\Http\Controllers\CustomerAuthController;
use App\Modules\Identity\Http\Controllers\CustomerProfileController;
use App\Modules\Identity\Http\Middleware\SetLocaleMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware(['api', SetLocaleMiddleware::class])->group(function (): void {
    // Public auth endpoints — IP-throttled to limit brute-force / spam.
    // The OTP endpoint is also phone-rate-limited inside OtpRateLimiter (FR-I15).
    Route::middleware('throttle:10,1')->group(function (): void {
        Route::post('register/customer', [CustomerAuthController::class, 'register']);
        Route::post('phone/otp/send', [CustomerAuthController::class, 'sendOtp']);
        Route::post('phone/verify', [CustomerAuthController::class, 'verifyPhone']);
    });

    Route::post('login', [CustomerAuthController::class, 'login'])->middleware('throttle:6,1');

    Route::middleware(['auth:sanctum', 'ensure.account.active'])->group(function (): void {
        Route::post('logout', [CustomerAuthController::class, 'logout']);

        Route::middleware('role:customer')->prefix('customer')->group(function (): void {
            Route::get('profile', [CustomerProfileController::class, 'show']);
            Route::put('profile', [CustomerProfileController::class, 'update']);

            Route::post('addresses', [CustomerAddressController::class, 'store']);
            Route::delete('addresses/{customerAddress}', [CustomerAddressController::class, 'destroy']);
        });
    });
});
