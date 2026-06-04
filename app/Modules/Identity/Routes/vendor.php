<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\VendorAccountController;
use App\Modules\Identity\Http\Controllers\VendorBusinessHourController;
use App\Modules\Identity\Http\Controllers\VendorComplianceController;
use App\Modules\Identity\Http\Controllers\VendorCoverageAreaController;
use App\Modules\Identity\Http\Controllers\VendorDocumentController;
use App\Modules\Identity\Http\Controllers\VendorProfileController;
use App\Modules\Identity\Http\Controllers\VendorProfileResubmitController;
use App\Modules\Identity\Http\Controllers\VendorRegistrationController;
use App\Modules\Identity\Http\Middleware\SetLocaleMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware(['api', SetLocaleMiddleware::class])->group(function (): void {
    // Public registration — IP-throttled.
    Route::post('register/vendor', [VendorRegistrationController::class, 'register'])->middleware('throttle:10,1');

    // Authenticated vendor endpoints.
    Route::middleware(['auth:sanctum', 'role:vendor'])->prefix('vendor')->group(function (): void {
        Route::get('profile', [VendorProfileController::class, 'show']);
        Route::put('profile', [VendorProfileController::class, 'update']);

        // G3 — per-type approval + document expiry view (ADR-0021).
        Route::get('compliance', VendorComplianceController::class);

        Route::get('documents', [VendorDocumentController::class, 'index']);
        Route::post('documents', [VendorDocumentController::class, 'store']);
        Route::get('documents/{publicId}/signed-url', [VendorDocumentController::class, 'signedUrl']);

        Route::post('coverage-areas', [VendorCoverageAreaController::class, 'store']);

        Route::put('business-hours', [VendorBusinessHourController::class, 'update']);

        Route::post('vendor-profiles/{publicId}/resubmit', [VendorProfileResubmitController::class, 'store']);

        Route::post('account/password', [VendorAccountController::class, 'changePassword']);
        Route::post('account/email', [VendorAccountController::class, 'updateEmail']);
        Route::post('account/phone', [VendorAccountController::class, 'updatePhone']);
    });
});
