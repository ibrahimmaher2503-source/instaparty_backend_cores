<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the storefront locale from the `{locale}` URL prefix (/en/..., /ar/...).
 *
 * Deliberately separate from Identity's SetLocaleMiddleware, which resolves
 * ?lang= -> Accept-Language -> user preference for the 91 /api/v1/customer/*
 * endpoints. That precedence is correct for an API consumed by Flutter and is
 * covered by API tests; it is wrong for a storefront, where the locale must be
 * part of the URL so each language is independently indexable.
 *
 * Livewire interop: this middleware does NOT need to be registered as Livewire
 * persistent middleware. POST /livewire/update carries no {locale} prefix, but
 * Livewire 3 stores the resolved locale in the component snapshot memo and
 * restores it on hydrate (Livewire\Features\SupportLocales\SupportLocales), so
 * the locale survives interactions on its own. Verified against a real HTTP
 * round-trip in tests/Feature/Storefront/StorefrontLocaleLivewireTest.php.
 * (This was a genuine problem in Livewire 2 — it is not one in v3.)
 */
class SetStorefrontLocaleMiddleware
{
    public const SUPPORTED_LOCALES = ['en', 'ar'];

    public const DEFAULT_LOCALE = 'en';

    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($this->resolveLocale($request));

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        // The route parameter is authoritative. On Livewire update requests this
        // still resolves: Livewire rebuilds the original URI and re-matches the
        // route before running persistent middleware.
        $routeLocale = $request->route('locale');

        if (is_string($routeLocale) && $this->isSupported($routeLocale)) {
            return $routeLocale;
        }

        // Fallback for any context where the route is not yet resolved.
        $segment = $request->segment(1);

        if (is_string($segment) && $this->isSupported($segment)) {
            return $segment;
        }

        return self::DEFAULT_LOCALE;
    }

    private function isSupported(string $locale): bool
    {
        return in_array($locale, self::SUPPORTED_LOCALES, true);
    }
}
