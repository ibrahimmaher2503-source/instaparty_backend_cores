<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Middleware\SetLocaleMiddleware;
use App\Modules\Shared\Http\Controllers\CmsPageController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware(['api', SetLocaleMiddleware::class])->group(function (): void {
    Route::get('/cms/pages/{slug}', [CmsPageController::class, 'show']);
});
