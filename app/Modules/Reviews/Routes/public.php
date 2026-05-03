<?php

declare(strict_types=1);

use App\Modules\Reviews\Http\Controllers\Public\GetServiceRatingSummaryController;
use App\Modules\Reviews\Http\Controllers\Public\GetVendorRatingSummaryController;
use App\Modules\Reviews\Http\Controllers\Public\ListServiceReviewsController;
use App\Modules\Reviews\Http\Controllers\Public\ListVendorReviewsController;
use Illuminate\Support\Facades\Route;

Route::prefix('public')
    ->group(function (): void {
        Route::get(
            'services/{servicePublicId}/reviews',
            [ListServiceReviewsController::class, 'index']
        )->name('reviews.service.public.index');

        Route::get(
            'services/{servicePublicId}/rating-summary',
            [GetServiceRatingSummaryController::class, 'show']
        )->name('reviews.service.rating-summary');

        Route::get(
            'vendors/{vendorPublicId}/reviews',
            [ListVendorReviewsController::class, 'index']
        )->name('reviews.vendor.public.index');

        Route::get(
            'vendors/{vendorPublicId}/rating-summary',
            [GetVendorRatingSummaryController::class, 'show']
        )->name('reviews.vendor.rating-summary');
    });
