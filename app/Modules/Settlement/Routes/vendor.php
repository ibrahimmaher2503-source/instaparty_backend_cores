<?php

declare(strict_types=1);

use App\Modules\Settlement\Http\Controllers\Vendor\WalletController;
use App\Modules\Settlement\Http\Controllers\Vendor\WithdrawalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('api/v1/vendor')->group(function (): void {
    // US2 — Wallet balance & ledger
    Route::middleware('permission:settlement.view_wallet.own')->group(function (): void {
        Route::get('/wallet', [WalletController::class, 'show']);
        Route::get('/wallet/ledger', [WalletController::class, 'ledger']);
    });

    // US3 — Withdrawals
    Route::middleware('permission:settlement.view_withdrawals.own')->group(function (): void {
        Route::get('/withdrawals', [WithdrawalController::class, 'index']);
        Route::get('/withdrawals/{public_id}', [WithdrawalController::class, 'show']);
    });

    Route::post('/withdrawals', [WithdrawalController::class, 'store'])
        ->middleware(['permission:settlement.request_withdrawal.own', 'idempotency']);
});
