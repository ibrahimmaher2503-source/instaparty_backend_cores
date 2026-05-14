<?php

declare(strict_types=1);

use App\Modules\Shared\Http\Controllers\VendorChangeRequestListController;
use App\Modules\Shared\Http\Middleware\SetLocaleMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware(['api', SetLocaleMiddleware::class])->group(function (): void {
    Route::middleware(['auth:sanctum', 'role:vendor'])->prefix('vendor')->group(function (): void {
        Route::get('change-requests', [VendorChangeRequestListController::class, 'index']);
    });
});
