<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Middleware\SetLocaleMiddleware;
use App\Modules\Shared\Http\Controllers\BrandingController;
use App\Modules\Shared\Http\Controllers\CmsPageController;
use App\Modules\Shared\Http\Controllers\DesignTokenController;
use App\Modules\Shared\Http\Controllers\FeatureFlagController;
use App\Modules\Shared\Http\Controllers\HomepageController;
use App\Modules\Shared\Http\Controllers\NavigationMenuController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware(['api', SetLocaleMiddleware::class])->group(function (): void {
    Route::get('/cms/pages/{slug}', [CmsPageController::class, 'show']);
    Route::get('/cms/homepage', [HomepageController::class, 'index']);
    Route::get('/theme/tokens', [DesignTokenController::class, 'show']);
    Route::get('/theme/branding', [BrandingController::class, 'show']);
    Route::get('/theme/menus', [NavigationMenuController::class, 'show']);
    Route::get('/feature-flags/public', [FeatureFlagController::class, 'index']);
});
