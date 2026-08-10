{{--
    Storefront base layout.

    Direction and language are derived directly from the resolved locale rather
    than from shared view state, so the layout stands on its own.

    RTL is handled by Tailwind 4 logical properties (ms-*/me-*/ps-*/pe-*/start-*/
    end-*) rather than a separate stylesheet — never use left/right utilities in
    storefront views, or Arabic will silently mirror wrong.
--}}
@php
    $locale = app()->getLocale();
    $direction = $locale === 'ar' ? 'rtl' : 'ltr';
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', config('app.name'))</title>

    {{-- Each locale is its own indexable URL; tell crawlers they are alternates. --}}
    @foreach ($storefrontLocaleUrls ?? [] as $alternate => $alternateUrl)
        <link rel="alternate" hreflang="{{ $alternate }}" href="{{ $alternateUrl }}">
    @endforeach

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white text-gray-900 antialiased">
    @include('storefront.partials.header')

    <main>
        @yield('content')
    </main>

    @include('storefront.partials.footer')
</body>
</html>
