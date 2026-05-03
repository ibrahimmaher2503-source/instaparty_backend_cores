<?php

declare(strict_types=1);

use App\Modules\Communication\Http\Controllers\NotificationPreferenceController;
use Illuminate\Support\Facades\Route;

Route::middleware('role:customer')->group(function () {
    Route::get('/notification-preferences', [NotificationPreferenceController::class, 'indexCustomer']);
    Route::put('/notification-preferences/{channel}/{event_category}', [NotificationPreferenceController::class, 'updateCustomer']);
});
