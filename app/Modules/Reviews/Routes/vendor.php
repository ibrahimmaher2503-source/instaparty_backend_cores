<?php

declare(strict_types=1);

use App\Modules\Reviews\Http\Controllers\Vendor\RespondToReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:vendor'])
    ->prefix('vendor')
    ->group(function (): void {
        Route::post(
            'reviews/{reviewType}/{publicId}/respond',
            [RespondToReviewController::class, 'store']
        )->name('reviews.vendor.respond');
    });
