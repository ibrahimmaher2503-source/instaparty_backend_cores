<?php

declare(strict_types=1);

use App\Modules\Settlement\Http\Controllers\Admin\ReconciliationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:admin', 'can:audit.view'])
    ->prefix('api/v1/admin/settlement')
    ->group(function (): void {
        Route::get('/reconciliation/status', [ReconciliationController::class, 'status']);

        Route::post('/reconciliation/trigger', [ReconciliationController::class, 'trigger'])
            ->middleware('idempotency');
    });
