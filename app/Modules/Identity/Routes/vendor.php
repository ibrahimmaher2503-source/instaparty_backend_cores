<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\VendorAccountController;
use App\Modules\Identity\Http\Controllers\VendorBusinessHourController;
use App\Modules\Identity\Http\Controllers\VendorComplianceAuditLogController;
use App\Modules\Identity\Http\Controllers\VendorComplianceController;
use App\Modules\Identity\Http\Controllers\VendorCoverageAreaController;
use App\Modules\Identity\Http\Controllers\VendorDocumentController;
use App\Modules\Identity\Http\Controllers\VendorMeController;
use App\Modules\Identity\Http\Controllers\VendorProfileController;
use App\Modules\Identity\Http\Controllers\VendorProfileMediaController;
use App\Modules\Identity\Http\Controllers\VendorProfileResubmitController;
use App\Modules\Identity\Http\Controllers\VendorRegistrationController;
use App\Modules\Identity\Http\Middleware\SetLocaleMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware(['api', SetLocaleMiddleware::class])->group(function (): void {
    // Public registration — IP-throttled.
    Route::post('register/vendor', [VendorRegistrationController::class, 'register'])->middleware('throttle:10,1');

    // Authenticated vendor endpoints.
    Route::middleware(['auth:sanctum', 'role:vendor'])->prefix('vendor')->group(function (): void {
        // G2 — role-aware identity payload for the vendor app.
        Route::get('me', VendorMeController::class);

        Route::get('profile', [VendorProfileController::class, 'show']);
        Route::put('profile', [VendorProfileController::class, 'update']);

        // Vendor-portal 2.3–2.6 + 2.9/2.10 — branding + portfolio media.
        Route::post('profile/{kind}', [VendorProfileMediaController::class, 'uploadBranding'])->whereIn('kind', ['logo', 'cover']);
        Route::delete('profile/{kind}', [VendorProfileMediaController::class, 'deleteBranding'])->whereIn('kind', ['logo', 'cover']);
        Route::get('profile/portfolio', [VendorProfileMediaController::class, 'listPortfolio']);
        Route::post('profile/portfolio', [VendorProfileMediaController::class, 'uploadPortfolio']);
        Route::delete('profile/portfolio/{mediaPublicId}', [VendorProfileMediaController::class, 'deletePortfolio']);

        // G3 — per-type approval + document expiry view (ADR-0021).
        Route::get('compliance', VendorComplianceController::class);
        Route::get('compliance/audit-log', VendorComplianceAuditLogController::class);

        Route::get('documents', [VendorDocumentController::class, 'index']);
        Route::post('documents', [VendorDocumentController::class, 'store']);
        Route::get('documents/{publicId}/signed-url', [VendorDocumentController::class, 'signedUrl']);

        // Coverage areas — full CRUD (vendor-portal 9.1–9.5; only POST existed).
        Route::get('coverage-areas', [VendorCoverageAreaController::class, 'index']);
        Route::post('coverage-areas', [VendorCoverageAreaController::class, 'store']);
        Route::get('coverage-areas/available-cities', [VendorCoverageAreaController::class, 'availableCities']);
        Route::patch('coverage-areas/{cityId}', [VendorCoverageAreaController::class, 'update'])->whereNumber('cityId');
        Route::delete('coverage-areas/{cityId}', [VendorCoverageAreaController::class, 'destroy'])->whereNumber('cityId');

        // Business hours — GET added (vendor-portal 8.1; PUT existed without a read).
        Route::get('business-hours', [VendorBusinessHourController::class, 'index']);
        Route::put('business-hours', [VendorBusinessHourController::class, 'update']);

        Route::post('vendor-profiles/{publicId}/resubmit', [VendorProfileResubmitController::class, 'store']);

        Route::post('account/password', [VendorAccountController::class, 'changePassword']);
        Route::post('account/email', [VendorAccountController::class, 'updateEmail']);
        Route::post('account/phone', [VendorAccountController::class, 'updatePhone']);
    });
});
