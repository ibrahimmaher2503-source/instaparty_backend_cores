<?php

declare(strict_types=1);

use App\Modules\Discovery\Http\Controllers\Customer\ServiceSearchController;
use App\Modules\Discovery\Http\Controllers\Customer\WishlistController;
use Illuminate\Support\Facades\Route;

// Public search endpoint — no auth required
Route::prefix('api/v1/customer')->group(function (): void {
    Route::get('services', ServiceSearchController::class);
});

// Authenticated wishlist endpoints
Route::middleware(['auth:sanctum'])->prefix('api/v1/customer')->group(function (): void {
    Route::get('wishlist', [WishlistController::class, 'index']);
    Route::post('wishlist/items', [WishlistController::class, 'add']);
    Route::delete('wishlist/items/{servicePublicId}', [WishlistController::class, 'remove']);
});
