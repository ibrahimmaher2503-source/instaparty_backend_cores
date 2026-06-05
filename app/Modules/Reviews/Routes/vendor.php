<?php

declare(strict_types=1);

use App\Modules\Reviews\Http\Controllers\Vendor\RespondToReviewController;
use App\Modules\Reviews\Http\Controllers\Vendor\VendorReviewInboxController;
use App\Modules\Reviews\Http\Controllers\Vendor\VendorReviewStatsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:vendor'])
    ->prefix('vendor')
    ->group(function (): void {
        Route::get('reviews', [VendorReviewInboxController::class, 'index'])
            ->name('reviews.vendor.index');
        Route::get('reviews/stats', VendorReviewStatsController::class)
            ->name('reviews.vendor.stats');
        Route::get('reviews/{reviewType}/{publicId}', [VendorReviewInboxController::class, 'show'])
            ->whereIn('reviewType', ['service', 'vendor'])
            ->name('reviews.vendor.show');
        Route::post(
            'reviews/{reviewType}/{publicId}/respond',
            [RespondToReviewController::class, 'store']
        )->name('reviews.vendor.respond');
    });
