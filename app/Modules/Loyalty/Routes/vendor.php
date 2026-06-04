<?php

declare(strict_types=1);

use App\Modules\Loyalty\Http\Controllers\Vendor\LoyaltyProgramController;
use Illuminate\Support\Facades\Route;

// role:vendor added (P0 hardening, vendor-portal audit 2026-06-04: a
// customer token could read AND attempt writes on the loyalty program).
Route::middleware(['auth:sanctum', 'role:vendor', 'idempotency'])
    ->prefix('vendor/loyalty')
    ->group(function () {
        Route::post('program', [LoyaltyProgramController::class, 'store']);
        Route::get('program', [LoyaltyProgramController::class, 'show'])->withoutMiddleware(['idempotency']);
        Route::put('program', [LoyaltyProgramController::class, 'update']);
    });
