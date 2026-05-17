<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Middleware\SetLocaleMiddleware;
use App\Modules\Shared\Http\Controllers\BrandingController;
use App\Modules\Shared\Http\Controllers\CmsPageController;
use App\Modules\Shared\Http\Controllers\DesignTokenController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware(['api', SetLocaleMiddleware::class])->group(function (): void {
    Route::get('/cms/pages/{slug}', [CmsPageController::class, 'show']);
    Route::get('/theme/tokens', [DesignTokenController::class, 'show']);
    Route::get('/theme/branding', [BrandingController::class, 'show']);
});
