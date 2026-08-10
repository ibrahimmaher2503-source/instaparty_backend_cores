<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
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
        $locale = $this->resolveLocale($request);

        App::setLocale($locale);

        // Let route() generate /{locale}/... without every call site passing it.
        URL::defaults(['locale' => $locale]);

        // Drop {locale} from the route's parameter bag so controllers never receive
        // it. Laravel fills controller arguments POSITIONALLY, so a prefixed route
        // would otherwise pass "ar" as the first scalar argument — a
        // ServicePageController::__invoke(string $publicId) would silently get the
        // locale instead of the ULID. This project has already been bitten by the
        // sibling footgun (Route::defaults() swapping positional args), so keep the
        // parameter out of the bag entirely rather than adding $locale to 35
        // controller signatures.
        $request->route()?->forgetParameter('locale');

        // Single source of truth for "this same page, in the other language",
        // consumed by both the <link rel="alternate"> tags and the header switcher.
        view()->share('storefrontLocaleUrls', $this->alternateUrls($request));

        return $next($request);
    }

    /**
     * Builds the equivalent URL of the current page in every supported locale.
     *
     * Swaps the leading path segment rather than using the matched route, because
     * Route::uri() yields the route PATTERN ("{locale}/services/{publicId}"), not
     * the resolved path — using it produces hrefs like /en/{locale}.
     *
     * @return array<string, string>
     */
    private function alternateUrls(Request $request): array
    {
        $segments = $request->segments();
        $query = $request->getQueryString();

        $urls = [];

        foreach (self::SUPPORTED_LOCALES as $locale) {
            // Every storefront route is {locale}-prefixed, so segment 0 is the locale.
            $segments[0] = $locale;

            $urls[$locale] = url(implode('/', $segments)).($query !== null ? '?'.$query : '');
        }

        return $urls;
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
