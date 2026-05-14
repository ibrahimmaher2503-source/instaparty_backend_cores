<?php

declare(strict_types=1);

use App\Modules\Catalog\Http\Controllers\ServiceChangeRequestController;
use App\Modules\Identity\Http\Middleware\SetLocaleMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/admin')->middleware(['api', SetLocaleMiddleware::class, 'auth:sanctum', 'role:admin'])->group(function (): void {
    Route::post('services/{service:public_id}/request-changes', [ServiceChangeRequestController::class, 'store'])
        ->middleware('can:update,service')
        ->name('admin.services.request-changes');
});
