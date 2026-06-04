<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\CustomerAddressController;
use App\Modules\Identity\Http\Controllers\CustomerAuthController;
use App\Modules\Identity\Http\Controllers\CustomerProfileController;
use App\Modules\Identity\Http\Controllers\DeviceController;
use App\Modules\Identity\Http\Controllers\VendorWishlistController;
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

    Route::post('login', [CustomerAuthController::class, 'login'])->middleware('throttle:api-login');

    // Feature 054 (B1, US1) — Forgot-password loop. Request side is
    // identifier-throttled; confirm side is IP-throttled.
    Route::post('password/reset/request', [CustomerAuthController::class, 'requestPasswordReset'])
        ->middleware('throttle:password-reset');
    Route::post('password/reset/confirm', [CustomerAuthController::class, 'confirmPasswordReset'])
        ->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'ensure.account.active'])->group(function (): void {
        Route::post('logout', [CustomerAuthController::class, 'logout']);

        // FCM device registration — shared by customer and vendor apps (G12).
        Route::post('devices', [DeviceController::class, 'store']);
        Route::delete('devices/{fcmToken}', [DeviceController::class, 'destroy']);

        Route::middleware('role:customer')->prefix('customer')->group(function (): void {
            Route::get('profile', [CustomerProfileController::class, 'show']);
            Route::put('profile', [CustomerProfileController::class, 'update']);

            Route::get('addresses', [CustomerAddressController::class, 'index']);
            Route::post('addresses', [CustomerAddressController::class, 'store']);
            Route::patch('addresses/{customerAddress}', [CustomerAddressController::class, 'update']);
            Route::post('addresses/{customerAddress}/set-default', [CustomerAddressController::class, 'setDefault']);
            Route::delete('addresses/{customerAddress}', [CustomerAddressController::class, 'destroy']);

            // Vendor Wishlist (C5, C6, C7)
            Route::prefix('wishlist/vendors')->group(function (): void {
                Route::get('/', [VendorWishlistController::class, 'index'])->name('wishlist.vendors.index');
                Route::post('/', [VendorWishlistController::class, 'store'])->name('wishlist.vendors.store');
                Route::delete('{vendorPublicId}', [VendorWishlistController::class, 'destroy'])->name('wishlist.vendors.destroy');
            });
        });
    });
});
