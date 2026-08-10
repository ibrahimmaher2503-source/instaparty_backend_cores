@php
    use App\Modules\Shared\Http\Middleware\SetStorefrontLocaleMiddleware;

    $current = app()->getLocale();

    // Swap the leading locale segment so the switcher keeps the visitor on the
    // page they are already reading, instead of dumping them on the home page.
    $segments = request()->segments();
    $localeNames = ['en' => 'English', 'ar' => 'العربية'];
@endphp

<header class="border-b border-gray-200">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4">
        <a href="{{ route('storefront.home') }}" class="text-lg font-semibold">
            {{ config('app.name') }}
        </a>

        <nav class="flex items-center gap-2" aria-label="{{ __('storefront.nav.language') }}">
            @foreach (SetStorefrontLocaleMiddleware::SUPPORTED_LOCALES as $locale)
                @php
                    $target = $segments;
                    $target[0] = $locale;
                @endphp

                <a
                    href="{{ url(implode('/', $target)) }}"
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
