<?php

declare(strict_types=1);

use App\Modules\Payments\Http\Controllers\Webhook\PaymobWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/api/v1/webhooks/paymob', PaymobWebhookController::class);
