<?php

declare(strict_types=1);

use App\Modules\Geography\Http\Controllers\GeographyCustomerController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/customer')->middleware('api')->group(function (): void {
    Route::get('governorates', [GeographyCustomerController::class, 'governorates']);
    Route::get('governorates/{governoratePublicId}/regions', [GeographyCustomerController::class, 'regions']);
    Route::get('regions/{regionPublicId}/cities', [GeographyCustomerController::class, 'regionCities']);
    Route::get('cities', [GeographyCustomerController::class, 'cities']);
});
