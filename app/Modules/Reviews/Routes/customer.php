<?php

declare(strict_types=1);

use App\Modules\Reviews\Http\Controllers\Customer\DeleteOwnReviewController;
use App\Modules\Reviews\Http\Controllers\Customer\ListMyReviewsController;
use App\Modules\Reviews\Http\Controllers\Customer\SubmitServiceReviewController;
use App\Modules\Reviews\Http\Controllers\Customer\SubmitVendorReviewController;
use App\Modules\Reviews\Http\Controllers\Customer\UpdateOwnReviewController;
use App\Modules\Reviews\Http\Controllers\Customer\VendorReviewsController;
use Illuminate\Support\Facades\Route;

// Unauthenticated customer-facing read routes
Route::prefix('customer')->group(function (): void {
    Route::get(
        'vendors/{vendorPublicId}/reviews',
        [VendorReviewsController::class, 'index']
    )->name('reviews.vendor.customer.index');
});

Route::middleware(['auth:sanctum', 'role:customer'])
    ->prefix('customer')
    ->group(function (): void {
        Route::post(
            'booking-items/{bookingItemPublicId}/review',
            [SubmitServiceReviewController::class, 'store']
        )->name('reviews.service.store');

        Route::post(
            'booking-vendors/{bookingVendorPublicId}/review',
            [SubmitVendorReviewController::class, 'store']
        )->name('reviews.vendor.store');

        Route::get(
            'reviews',
            [ListMyReviewsController::class, 'index']
        )->name('reviews.my.index');

        Route::patch(
            'reviews/{reviewType}/{publicId}',
            [UpdateOwnReviewController::class, 'update']
        )->name('reviews.update');

        Route::delete(
            'reviews/{reviewType}/{publicId}',
            [DeleteOwnReviewController::class, 'destroy']
        )->name('reviews.destroy');
    });
