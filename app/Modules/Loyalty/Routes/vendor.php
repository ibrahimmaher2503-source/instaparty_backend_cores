<?php

declare(strict_types=1);

use App\Modules\Loyalty\Http\Controllers\Vendor\LoyaltyProgramController;
use App\Modules\Loyalty\Http\Controllers\Vendor\LoyaltyRuleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'idempotency'])
    ->prefix('vendor/loyalty')
    ->group(function () {
        Route::post('program', [LoyaltyProgramController::class, 'store']);
        Route::get('program', [LoyaltyProgramController::class, 'show'])->withoutMiddleware(['idempotency']);
        Route::put('program', [LoyaltyProgramController::class, 'update']);
        Route::post('rules', [LoyaltyRuleController::class, 'store']);
    });
