<?php

declare(strict_types=1);

use App\Modules\Shared\Http\Middleware\SetStorefrontLocaleMiddleware;
use Illuminate\Support\Facades\Route;

/**
 * Covers the /{locale} storefront routing chain: the root redirect, locale
 * resolution from the URL prefix, rejection of unsupported locales, and the
 * two things most likely to break silently later — positional controller
 * arguments, and route() generation.
 */
beforeEach(function (): void {
    // A route shaped like the real ones (a prefixed route taking a scalar), used
    // to pin down argument binding and URL generation.
    Route::middleware(['web', SetStorefrontLocaleMiddleware::class])
        ->prefix('{locale}')
        ->whereIn('locale', SetStorefrontLocaleMiddleware::SUPPORTED_LOCALES)
        ->name('storefront.')
        ->group(function (): void {
            Route::get('_echo/{publicId}', fn (string $publicId) => response()->json([
                'received' => $publicId,
                'locale' => app()->getLocale(),
                'generated' => route('storefront._echo', ['publicId' => 'ABC']),
            ]))->name('_echo');
        });

    // Routes registered after the app has booted are matchable but absent from the
    // name lookup table, so route() would throw RouteNotFoundException.
    Route::getRoutes()->refreshNameLookups();
});

it('redirects the bare root to the default locale', function (): void {
    $this->get('/')->assertRedirect('/en');
})->group('storefront', 'routing');

it('honours Accept-Language when redirecting the bare root', function (): void {
    $this->get('/', ['Accept-Language' => 'ar'])->assertRedirect('/ar');
})->group('storefront', 'routing');

it('serves the home page for each supported locale', function (string $locale, string $dir): void {
    $response = $this->get("/{$locale}");

    $response->assertOk();
    expect($response->getContent())
        ->toContain('lang="'.$locale.'"')
        ->toContain('dir="'.$dir.'"');
})->with([
    'english' => ['en', 'ltr'],
    'arabic' => ['ar', 'rtl'],
])->group('storefront', 'routing', 'locale');

it('rejects an unsupported locale prefix', function (): void {
    $this->get('/fr')->assertNotFound();
})->group('storefront', 'routing');

/**
 * The regression this project has already been bitten by once.
 *
 * Laravel fills controller arguments POSITIONALLY. Because every storefront route
 * sits under a {locale} prefix, {locale} is the first route parameter — so a
 * controller like ServicePageController::__invoke(string $publicId) would receive
 * "ar" instead of the ULID unless the middleware drops it from the parameter bag.
 *
 * Related, and the reason this is asserted rather than assumed:
 * Route::defaults() has already caused a positional-argument swap on this codebase
 * (see the vendor-mobile audit). A wrong value here does not throw — it 404s or
 * silently loads the wrong record.
 */
it('passes the real parameter to the controller, not the locale', function (): void {
    $payload = $this->get('/ar/_echo/01JQZX9WSHULID000000000000')->json();

    expect($payload['received'])->toBe('01JQZX9WSHULID000000000000');
    expect($payload['locale'])->toBe('ar');
})->group('storefront', 'routing');

it('generates locale-prefixed URLs without passing the locale explicitly', function (): void {
    $payload = $this->get('/ar/_echo/01JQZX9WSHULID000000000000')->json();

    // route() omits `locale`; URL::defaults() in the middleware supplies it.
    expect($payload['generated'])->toEndWith('/ar/_echo/ABC');
})->group('storefront', 'routing', 'locale');

it('keeps the visitor on the same page when switching locale', function (): void {
    $html = $this->get('/ar')->getContent();

    // The switcher swaps the leading segment rather than linking to the root.
    expect($html)
        ->toContain('hreflang="en"')
        ->toContain('hreflang="ar"');
})->group('storefront', 'routing', 'locale');
