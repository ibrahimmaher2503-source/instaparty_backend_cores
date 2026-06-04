<?php

declare(strict_types=1);

use App\Modules\Catalog\Http\Controllers\CategoryController;
use App\Modules\Catalog\Http\Controllers\Customer\ServiceDetailController;
use App\Modules\Catalog\Http\Controllers\OccasionController;
use App\Modules\Catalog\Http\Controllers\ServiceThemeController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/customer')->middleware('api')->group(function (): void {
    Route::get('occasions', [OccasionController::class, 'index']);
    Route::get('occasions/{slug}', [OccasionController::class, 'show']);
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{publicId}', [CategoryController::class, 'show']);
    Route::get('categories/{publicId}/field-schemas', [CategoryController::class, 'fieldSchemas']);
    Route::get('service-themes', [ServiceThemeController::class, 'index']);
    Route::get('services/{servicePublicId}', ServiceDetailController::class);
});
