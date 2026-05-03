<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:admin'])
    ->prefix('api/v1/admin')
    ->group(function (): void {
        // Admin booking routes
    });
