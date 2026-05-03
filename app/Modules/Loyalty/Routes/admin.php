<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Admin loyalty routes — all covered by Filament Resource / Pages
// HTTP API for admin reads (support tooling)
Route::middleware(['auth:sanctum'])
    ->prefix('admin/loyalty')
    ->group(function () {
        // Filament covers the full admin surface; REST endpoints here if needed by external tooling
    });
