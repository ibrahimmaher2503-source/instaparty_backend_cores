<?php

declare(strict_types=1);

use App\Modules\Catalog\Http\Controllers\CategoryController;
use App\Modules\Catalog\Http\Controllers\OccasionController;
use App\Modules\Catalog\Http\Controllers\Vendor\DigitalServiceController;
use App\Modules\Catalog\Http\Controllers\Vendor\RentalServiceController;
use App\Modules\Catalog\Http\Controllers\Vendor\SaleServiceController;
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
});
