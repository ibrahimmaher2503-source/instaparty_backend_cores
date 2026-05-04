<?php

declare(strict_types=1);

use App\Modules\Subscriptions\Http\Controllers\Vendor\ShowSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'ability:vendor'])->prefix('api/v1/vendor')->group(function () {
    Route::get('subscription', ShowSubscriptionController::class);
});
