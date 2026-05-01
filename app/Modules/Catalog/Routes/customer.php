<?php

declare(strict_types=1);

use App\Modules\Catalog\Http\Controllers\CategoryController;
use App\Modules\Catalog\Http\Controllers\OccasionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('api/v1/customer')->group(function (): void {
    Route::get('occasions', [OccasionController::class, 'index']);
    Route::get('categories', [CategoryController::class, 'index']);
});
