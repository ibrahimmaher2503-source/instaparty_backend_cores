<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('admin')->group(function () {
    // Admin-facing Catalog routes — Filament resources are panel-registered separately
});
