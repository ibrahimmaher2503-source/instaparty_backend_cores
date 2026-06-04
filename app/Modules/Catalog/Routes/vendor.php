<?php

declare(strict_types=1);

use App\Modules\Catalog\Http\Controllers\DownloadFailedRowsController;
use App\Modules\Catalog\Http\Controllers\DownloadImportTemplateController;
use App\Modules\Catalog\Http\Controllers\OccasionController;
use App\Modules\Catalog\Http\Controllers\ServiceResubmitController;
use App\Modules\Catalog\Http\Controllers\Vendor\DigitalServiceController;
use App\Modules\Catalog\Http\Controllers\Vendor\ImportDigitalServicesController;
use App\Modules\Catalog\Http\Controllers\Vendor\ImportRentalServicesController;
use App\Modules\Catalog\Http\Controllers\Vendor\ImportSaleServicesController;
use App\Modules\Catalog\Http\Controllers\Vendor\RentalServiceController;
use App\Modules\Catalog\Http\Controllers\Vendor\SaleServiceController;
use App\Modules\Catalog\Http\Controllers\Vendor\VendorArchiveServiceController;
use App\Modules\Catalog\Http\Controllers\Vendor\VendorCategoryController;
use App\Modules\Catalog\Http\Controllers\Vendor\VendorImportStatusController;
use App\Modules\Catalog\Http\Controllers\Vendor\VendorServiceChangeRequestController;
use App\Modules\Catalog\Http\Controllers\Vendor\VendorServiceLifecycleController;
use App\Modules\Catalog\Http\Controllers\Vendor\VendorServiceListController;
use App\Modules\Catalog\Http\Controllers\Vendor\VendorServiceMediaController;
use App\Modules\Catalog\Http\Controllers\Vendor\VendorServiceShowController;
use Illuminate\Support\Facades\Route;

// Template downloads are static files, but they live on the vendor surface —
// vendor role required (P0 hardening, vendor-portal audit 2026-06-04).
Route::middleware(['api', 'auth:sanctum', 'role:vendor'])->prefix('api/v1/vendor')->group(function (): void {
    Route::get('catalog/import/template/{type}', DownloadImportTemplateController::class)
        ->name('vendor.catalog.import.template');
});

// role:vendor added route-wide (P0 hardening, vendor-portal audit 2026-06-04:
// customer tokens previously got 200 on vendor reads; mutations were only
// saved by FormRequest authorize()).
Route::middleware(['api', 'auth:sanctum', 'role:vendor'])->prefix('api/v1/vendor')->group(function (): void {
    Route::get('occasions', [OccasionController::class, 'index']);
    Route::get('categories', [VendorCategoryController::class, 'index']);

    // Service list (M-02)
    Route::get('services', VendorServiceListController::class);
    Route::get('services/{publicId}', VendorServiceShowController::class)
        ->where('publicId', '[0-9A-HJKMNP-TV-Z]{26}');

    // Rental services
    Route::post('services/rental', [RentalServiceController::class, 'store']);
    Route::patch('services/rental/{publicId}', [RentalServiceController::class, 'update'])->name('vendor.services.rental.update');
    Route::delete('services/rental/{publicId}', [VendorArchiveServiceController::class, '__invoke'])->defaults('type', 'rental');

    // Sale services
    Route::post('services/sale', [SaleServiceController::class, 'store']);
    Route::patch('services/sale/{publicId}', [SaleServiceController::class, 'update'])->name('vendor.services.sale.update');
    Route::delete('services/sale/{publicId}', [VendorArchiveServiceController::class, '__invoke'])->defaults('type', 'sale');

    // Digital services
    Route::post('services/digital', [DigitalServiceController::class, 'store']);
    Route::patch('services/digital/{publicId}', [DigitalServiceController::class, 'update'])->name('vendor.services.digital.update');
    Route::delete('services/digital/{publicId}', [VendorArchiveServiceController::class, '__invoke'])->defaults('type', 'digital');

    // Excel imports
    Route::post('services/rental/import', [ImportRentalServicesController::class, 'store'])->name('vendor.services.rental.import');
    Route::post('services/sale/import', [ImportSaleServicesController::class, 'store'])->name('vendor.services.sale.import');
    Route::post('services/digital/import', [ImportDigitalServicesController::class, 'store'])->name('vendor.services.digital.import');

    // Service change request resubmission
    Route::post('services/{service:public_id}/resubmit', [ServiceResubmitController::class, 'store'])->name('vendor.services.resubmit');

    // Service change request — vendor clarification reply
    Route::post('service-change-requests/{publicId}/reply', [VendorServiceChangeRequestController::class, 'reply'])
        ->name('vendor.service-change-requests.reply');

    // Failed rows export
    Route::get('catalog/import/{importPublicId}/failed-rows', DownloadFailedRowsController::class)
        ->name('vendor.catalog.import.failed-rows');

    // Import status polling (vendor-portal 5.3).
    Route::get('catalog/import/{importPublicId}', VendorImportStatusController::class)
        ->name('vendor.catalog.import.status');

    // Service lifecycle as REST (vendor-portal 4.10 + 4.12 — spec 12 §12 parity).
    Route::post('services/{publicId}/submit-review', [VendorServiceLifecycleController::class, 'submitReview'])
        ->name('vendor.services.submit-review');
    Route::post('services/{publicId}/clone', [VendorServiceLifecycleController::class, 'clone'])
        ->name('vendor.services.clone');

    // Service.gallery media (spec 048-media-collections-phase1 US1).
    Route::prefix('services/{service:public_id}/media')->group(function (): void {
        Route::post('/', [VendorServiceMediaController::class, 'upload'])->name('vendor.services.media.upload');
        Route::get('/', [VendorServiceMediaController::class, 'list'])->name('vendor.services.media.list');
        Route::patch('order', [VendorServiceMediaController::class, 'reorder'])->name('vendor.services.media.reorder');
        Route::delete('{mediaPublicId}', [VendorServiceMediaController::class, 'destroy'])->name('vendor.services.media.destroy');
    });
});
