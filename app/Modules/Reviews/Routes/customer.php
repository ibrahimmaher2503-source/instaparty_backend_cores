<?php

declare(strict_types=1);

use App\Modules\Reviews\Http\Controllers\Customer\DeleteOwnReviewController;
use App\Modules\Reviews\Http\Controllers\Customer\ListMyReviewsController;
use App\Modules\Reviews\Http\Controllers\Customer\SubmitServiceReviewController;
use App\Modules\Reviews\Http\Controllers\Customer\SubmitVendorReviewController;
use Illuminate\Support\Facades\Route;

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

        Route::delete(
            'reviews/{reviewType}/{publicId}',
            [DeleteOwnReviewController::class, 'destroy']
        )->name('reviews.destroy');
    });
