<?php

declare(strict_types=1);

use App\Modules\Catalog\Http\Controllers\CategoryController;
use App\Modules\Catalog\Http\Controllers\Customer\ServiceDetailController;
use App\Modules\Catalog\Http\Controllers\Customer\ServiceDiscoveryExtrasController;
use App\Modules\Catalog\Http\Controllers\OccasionController;
use App\Modules\Catalog\Http\Controllers\ServiceThemeController;
use Illuminate\Support\Facades\Route;

// Phase 3 E (audit 2026-06-04): public catalog now resolves Accept-Language
// via the locale middleware and is rate-limited per IP.
Route::prefix('api/v1/customer')->middleware(['api', 'locale', 'throttle:60,1'])->group(function (): void {
    Route::get('occasions', [OccasionController::class, 'index']);
    Route::get('occasions/{slug}', [OccasionController::class, 'show']);
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{publicId}', [CategoryController::class, 'show']);
    Route::get('categories/{publicId}/field-schemas', [CategoryController::class, 'fieldSchemas']);
    Route::get('service-themes', [ServiceThemeController::class, 'index']);
    Route::get('services/suggestions', [ServiceDiscoveryExtrasController::class, 'suggestions']);
    Route::get('services/{servicePublicId}', ServiceDetailController::class);
    Route::get('services/{servicePublicId}/similar', [ServiceDiscoveryExtrasController::class, 'similar']);
    Route::post('services/{servicePublicId}/views', [ServiceDiscoveryExtrasController::class, 'trackView'])
        ->middleware('throttle:30,1');
});
