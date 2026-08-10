@php
    $current = app()->getLocale();

    // $storefrontLocaleUrls is shared by SetStorefrontLocaleMiddleware and points at
    // the SAME page in each locale, so switching language keeps the reader in place.
    $localeNames = ['en' => 'English', 'ar' => 'العربية'];
@endphp

{{--
    The Header Exception: sticky, translucent, no hard shadow. `sf-header` supplies
    the white/95 ground plus backdrop blur (storefront.css), keeping the flat-surface
    feel while still separating the header from scrolled content.
--}}
<header class="sf-header sticky top-0 z-40 border-b border-ink-200">
    <div class="mx-auto flex h-16 max-w-7xl items-center gap-6 px-4 sm:px-6">
        <a
            href="{{ route('storefront.home') }}"
            class="text-lg font-bold text-primary-500"
        >
            {{ __('storefront.common.site_name') }}
        </a>

        <div class="ms-auto flex items-center gap-1">
            {{--
                Locale switcher. A two-item segmented control rather than a dropdown:
                with exactly two languages a select costs an extra interaction and
                hides the alternative, and Arabic is co-primary here, not a setting
                buried behind a menu.
            --}}
            <nav class="flex items-center rounded-md border border-ink-200 p-0.5" aria-label="{{ __('storefront.nav.language') }}">
                @foreach ($storefrontLocaleUrls ?? [] as $locale => $localeUrl)
                    <a
                        href="{{ $localeUrl }}"
                        hreflang="{{ $locale }}"
                        lang="{{ $locale }}"
                        @if ($locale === $current) aria-current="true" @endif
                        @class([
                            'rounded-sm px-2.5 py-1 text-sm font-medium transition-colors duration-150',
                            'bg-primary-50 text-primary-500' => $locale === $current,
                            'text-ink-500 hover:text-ink-900' => $locale !== $current,
                        ])
                    >
                        {{ $localeNames[$locale] ?? $locale }}
                    </a>
                @endforeach
            </nav>

            @auth
                <form method="POST" action="{{ route('storefront.auth.logout') }}" class="ms-2">
                    @csrf
                    <button
                        type="submit"
                        class="rounded-md px-3 py-1.5 text-sm font-medium text-ink-500 transition-colors duration-150 hover:bg-ink-100 hover:text-ink-900"
                    >
                        {{ __('storefront.nav.logout') }}
                    </button>
                </form>
            @else
                <a
                    href="{{ route('storefront.auth.login') }}"
                    class="ms-2 rounded-md px-3 py-1.5 text-sm font-medium text-ink-500 transition-colors duration-150 hover:bg-ink-100 hover:text-ink-900"
                >
                    {{ __('storefront.nav.login') }}
                </a>

                <x-storefront.button :href="route('storefront.auth.register')" size="sm">
                    {{ __('storefront.nav.register') }}
                </x-storefront.button>
            @endauth
        </div>
    </div>
</header>
