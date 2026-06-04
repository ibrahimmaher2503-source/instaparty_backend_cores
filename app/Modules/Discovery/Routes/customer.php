<?php

declare(strict_types=1);

use App\Modules\Discovery\Http\Controllers\Customer\ServiceRecommendationController;
use App\Modules\Discovery\Http\Controllers\Customer\ServiceSearchController;
use App\Modules\Discovery\Http\Controllers\Customer\VendorBrowsingController;
use App\Modules\Discovery\Http\Controllers\Customer\VendorProfileIndexController;
use App\Modules\Discovery\Http\Controllers\Customer\VendorProfileShowController;
use App\Modules\Discovery\Http\Controllers\Customer\WishlistController;
use Illuminate\Support\Facades\Route;

// Public search endpoint — no auth required
Route::prefix('api/v1/customer')->middleware('api')->group(function (): void {
    Route::get('services', ServiceSearchController::class);
    Route::get('vendors', VendorProfileIndexController::class);
    Route::get('vendors/{publicId}', VendorProfileShowController::class);

    // Vendor browsing sub-resources (audit F8 / file 17a).
    Route::get('vendors/{publicId}/services', [VendorBrowsingController::class, 'services']);
    Route::get('vendors/{publicId}/coverage', [VendorBrowsingController::class, 'coverage']);
    Route::get('vendors/{publicId}/availability', [VendorBrowsingController::class, 'availability']);
    Route::post('vendors/{publicId}/availability/check', [VendorBrowsingController::class, 'availabilityCheck']);
    Route::get('vendors/{publicId}/portfolio', [VendorBrowsingController::class, 'portfolio']);

    // Wizard-context recommendations (Phase 3 ruling #1 — replaces packages).
    Route::post('discovery/recommendations', ServiceRecommendationController::class);
});

// Authenticated wishlist endpoints — customer-role only (P0 fix, audit 2026-06-04:
// vendor tokens could previously read/mutate a customer services-wishlist).
Route::middleware(['auth:sanctum', 'role:customer'])->prefix('api/v1/customer')->group(function (): void {
    Route::get('wishlist', [WishlistController::class, 'index']);
    Route::post('wishlist/items', [WishlistController::class, 'add']);
    Route::delete('wishlist/items/{servicePublicId}', [WishlistController::class, 'remove']);
});
