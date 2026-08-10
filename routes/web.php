<?php

declare(strict_types=1);

use App\Modules\Shared\Http\Middleware\SetStorefrontLocaleMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Storefront (Blade)
|--------------------------------------------------------------------------
|
| The customer-facing storefront. Every page lives under a /{locale} prefix so
| each language is independently indexable, mirroring the URL shape of the
| Next.js app being replaced.
|
| Module storefront routes are AUTO-DISCOVERED from each module's
| Routes/storefront.php — the same convention the admin panel uses for Filament
| resources. Each module therefore still owns its own routes (per
| .claude/rules/modules.md) without needing a loadRoutesFrom() line in ten
| separate ServiceProviders.
|
| The 91 /api/v1/customer/* endpoints are unaffected; they remain the Flutter
| mobile contract and are registered by each module's customer.php.
|
*/

Route::get('/', function (Request $request) {
    $locale = $request->getPreferredLanguage(SetStorefrontLocaleMiddleware::SUPPORTED_LOCALES)
        ?? SetStorefrontLocaleMiddleware::DEFAULT_LOCALE;

    return redirect('/'.$locale);
})->name('storefront.root');

Route::middleware(['web', SetStorefrontLocaleMiddleware::class])
    ->prefix('{locale}')
    ->whereIn('locale', SetStorefrontLocaleMiddleware::SUPPORTED_LOCALES)
    ->name('storefront.')
    ->group(function (): void {
        foreach (glob(app_path('Modules/*/Routes/storefront.php')) ?: [] as $moduleRoutes) {
            require $moduleRoutes;
        }
    });
