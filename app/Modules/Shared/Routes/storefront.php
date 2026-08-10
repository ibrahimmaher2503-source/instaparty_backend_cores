<?php

declare(strict_types=1);

use App\Modules\Shared\Http\Controllers\Web\HomeController;
use Illuminate\Support\Facades\Route;

/*
| Shared storefront routes.
|
| Auto-discovered by routes/web.php, which already applies the `web` middleware,
| the SetStorefrontLocaleMiddleware, the /{locale} prefix and the `storefront.`
| name prefix. Do NOT re-declare those here.
*/

Route::get('/', HomeController::class)->name('home');
