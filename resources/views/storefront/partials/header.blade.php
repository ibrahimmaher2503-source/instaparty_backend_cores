@php
    $current = app()->getLocale();

    // $storefrontLocaleUrls is shared by SetStorefrontLocaleMiddleware and points
    // at the SAME page in each locale, so switching language keeps the visitor
    // where they are instead of dumping them on the home page.
    $localeNames = ['en' => 'English', 'ar' => 'العربية'];
@endphp

<header class="border-b border-gray-200">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4">
        <a href="{{ route('storefront.home') }}" class="text-lg font-semibold">
            {{ config('app.name') }}
        </a>

        <nav class="flex items-center gap-2" aria-label="{{ __('storefront.nav.language') }}">
            @foreach ($storefrontLocaleUrls ?? [] as $locale => $localeUrl)
                <a
                    href="{{ $localeUrl }}"
                    hreflang="{{ $locale }}"
                    @class([
                        'rounded px-3 py-1 text-sm',
                        'bg-gray-900 text-white' => $locale === $current,
                        'text-gray-700 hover:bg-gray-100' => $locale !== $current,
                    ])
                    @if ($locale === $current) aria-current="true" @endif
                >
                    {{ $localeNames[$locale] ?? $locale }}
                </a>
            @endforeach
        </nav>
    </div>
</header>
