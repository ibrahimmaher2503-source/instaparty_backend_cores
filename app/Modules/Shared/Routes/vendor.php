<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Middleware\SetLocaleMiddleware;
use App\Modules\Shared\Http\Controllers\VendorChangeRequestListController;
use App\Modules\Shared\Http\Controllers\VendorDashboardSummaryController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware(['api', SetLocaleMiddleware::class])->group(function (): void {
    Route::middleware(['auth:sanctum', 'role:vendor'])->prefix('vendor')->group(function (): void {
        Route::get('change-requests', [VendorChangeRequestListController::class, 'index']);
        Route::get('dashboard/summary', VendorDashboardSummaryController::class);
    });
});
