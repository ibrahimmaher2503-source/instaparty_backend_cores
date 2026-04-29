<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\AdminVendorApprovalController;
use App\Modules\Identity\Http\Middleware\SetLocaleMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/admin')->middleware(['api', SetLocaleMiddleware::class, 'auth:sanctum', 'role:admin'])->group(function (): void {
    Route::get('vendor-profiles', [AdminVendorApprovalController::class, 'index']);
    Route::post('vendor-profiles/{vendorProfile}/approve', [AdminVendorApprovalController::class, 'approve']);
    Route::post('vendor-profiles/{vendorProfile}/reject', [AdminVendorApprovalController::class, 'reject']);
    Route::post('vendor-profiles/{vendorProfile}/approve-for-type', [AdminVendorApprovalController::class, 'approveForType']);
    Route::post('vendor-profiles/{vendorProfile}/revoke-type', [AdminVendorApprovalController::class, 'revokeType']);
    Route::post('vendor-profiles/{vendorProfile}/suspend', [AdminVendorApprovalController::class, 'suspend']);
});
