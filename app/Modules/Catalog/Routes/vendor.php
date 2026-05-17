<?php

declare(strict_types=1);

use App\Modules\Catalog\Http\Controllers\CategoryController;
use App\Modules\Catalog\Http\Controllers\OccasionController;
use App\Modules\Catalog\Http\Controllers\ServiceResubmitController;
use App\Modules\Catalog\Http\Controllers\Vendor\DigitalServiceController;
use App\Modules\Catalog\Http\Controllers\Vendor\ImportDigitalServicesController;
use App\Modules\Catalog\Http\Controllers\Vendor\ImportRentalServicesController;
use App\Modules\Catalog\Http\Controllers\Vendor\ImportSaleServicesController;
use App\Modules\Catalog\Http\Controllers\Vendor\RentalServiceController;
use App\Modules\Catalog\Http\Controllers\Vendor\SaleServiceController;
use App\Modules\Catalog\Http\Controllers\Vendor\VendorServiceChangeRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('api/v1/vendor')->group(function (): void {
    Route::get('occasions', [OccasionController::class, 'index']);
    Route::get('categories', [CategoryController::class, 'index']);

    // Rental services
    Route::post('services/rental', [RentalServiceController::class, 'store']);
    Route::patch('services/rental/{publicId}', [RentalServiceController::class, 'update'])->name('vendor.services.rental.update');

    // Sale services
    Route::post('services/sale', [SaleServiceController::class, 'store']);
    Route::patch('services/sale/{publicId}', [SaleServiceController::class, 'update'])->name('vendor.services.sale.update');

    // Digital services
    Route::post('services/digital', [DigitalServiceController::class, 'store']);
    Route::patch('services/digital/{publicId}', [DigitalServiceController::class, 'update'])->name('vendor.services.digital.update');

    // Excel imports
    Route::post('services/rental/import', [ImportRentalServicesController::class, 'store'])->name('vendor.services.rental.import');
    Route::post('services/sale/import', [ImportSaleServicesController::class, 'store'])->name('vendor.services.sale.import');
    Route::post('services/digital/import', [ImportDigitalServicesController::class, 'store'])->name('vendor.services.digital.import');

    // Service change request resubmission
    Route::post('services/{service:public_id}/resubmit', [ServiceResubmitController::class, 'store'])->name('vendor.services.resubmit');

    // Service change request — vendor clarification reply
    Route::post('service-change-requests/{publicId}/reply', [VendorServiceChangeRequestController::class, 'reply'])
        ->name('vendor.service-change-requests.reply');
});
