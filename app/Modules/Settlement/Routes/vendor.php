<?php

declare(strict_types=1);

use App\Modules\Settlement\Http\Controllers\Vendor\VendorBankAccountController;
use App\Modules\Settlement\Http\Controllers\Vendor\VendorCommissionRateController;
use App\Modules\Settlement\Http\Controllers\Vendor\VendorSettlementController;
use App\Modules\Settlement\Http\Controllers\Vendor\WalletController;
use App\Modules\Settlement\Http\Controllers\Vendor\WithdrawalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'vendor.not_suspended'])->prefix('api/v1/vendor')->group(function (): void {
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

    // Vendor-portal 10.3/10.4 — own resolved commission rates + calculator.
    Route::middleware('role:vendor')->group(function (): void {
        Route::get('/pricing/commission-rates', [VendorCommissionRateController::class, 'index']);
        Route::get('/pricing/calculator', [VendorCommissionRateController::class, 'calculator']);
    });

    // Vendor-portal 13.1–13.5 / G13 — bank accounts (schema approved 2026-06-05).
    Route::middleware('role:vendor')->prefix('bank-accounts')->group(function (): void {
        Route::get('/', [VendorBankAccountController::class, 'index']);
        Route::post('/', [VendorBankAccountController::class, 'store'])->middleware('idempotency');
        Route::patch('{publicId}', [VendorBankAccountController::class, 'update']);
        Route::delete('{publicId}', [VendorBankAccountController::class, 'destroy']);
        Route::post('{publicId}/set-default', [VendorBankAccountController::class, 'setDefault']);
    });

    // Vendor-portal 12.4/12.5 — settlement history (own commissions records).
    Route::middleware('permission:settlement.view_wallet.own')->group(function (): void {
        Route::get('/wallet/settlements', [VendorSettlementController::class, 'index']);
        Route::get('/wallet/settlements/{publicId}', [VendorSettlementController::class, 'show']);
    });
});
