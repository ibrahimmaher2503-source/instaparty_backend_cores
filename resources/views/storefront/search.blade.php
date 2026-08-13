@extends('storefront.layouts.app')

@php
    $selectedType = $filters['product_type'] ?? null;
    $selectedCategory = $filters['category_slug'] ?? null;
    $selectedOccasion = $filters['occasion'] ?? null;
    $selectedVendor = $filters['vendor'] ?? null;
    $selectedCity = $filters['city_public_id'] ?? null;
    $selectedDate = $filters['event_date'] ?? null;
    $priceMin = $filters['price_min'] ?? null;
    $priceMax = $filters['price_max'] ?? null;
@endphp

@section('title', __('storefront.search.title').' · '.__('storefront.common.site_name'))

@section('content')
    <div class="sf-search-page bg-[var(--sf-ivory)]">
        <section class="sf-search-hero border-b border-ink-200/80 bg-secondary-900 text-white">
            <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8 lg:py-20">
                <div class="max-w-3xl">
                    <p class="text-sm font-bold uppercase tracking-[0.22em] text-secondary-200">{{ __('storefront.nav.search') }}</p>
                    <h1 class="mt-4 text-4xl font-extrabold tracking-[-0.04em] sm:text-5xl">{{ __('storefront.search.title') }}</h1>
                    <p class="mt-5 max-w-2xl text-base leading-8 text-white/70">{{ __('storefront.home.hero.sub') }}</p>
                </div>

                <form action="{{ route('storefront.search') }}" method="GET" class="mt-9 flex flex-col gap-3 rounded-[1.4rem] bg-white p-3 shadow-deep sm:flex-row">
                    <label class="sr-only" for="catalog-search">{{ __('storefront.search.placeholder') }}</label>
                    <input id="catalog-search" name="q" value="{{ $filters['q'] ?? '' }}" type="search" placeholder="{{ __('storefront.search.placeholder') }}" class="sf-field min-h-14 flex-1 rounded-xl border-0 bg-ink-50 px-5 text-base text-ink-900 placeholder:text-ink-400 focus:ring-2 focus:ring-secondary-500">
                    @if ($selectedType)
                        <input type="hidden" name="product_type" value="{{ $selectedType }}">
                    @endif
                    @if ($selectedCategory)
                        <input type="hidden" name="category_slug" value="{{ $selectedCategory }}">
                    @endif
                    @if ($selectedOccasion)
                        <input type="hidden" name="occasion" value="{{ $selectedOccasion }}">
                    @endif
                    @if ($selectedVendor)
                        <input type="hidden" name="vendor" value="{{ $selectedVendor }}">
                    @endif
                    @if ($selectedCity)
                        <input type="hidden" name="city_public_id" value="{{ $selectedCity }}">
                    @endif
                    @if ($selectedDate)
                        <input type="hidden" name="event_date" value="{{ $selectedDate }}">
                    @endif
                    <button type="submit" class="sf-button sf-button--navy min-h-14 shrink-0 px-7">{{ __('storefront.common.search') }} <span aria-hidden="true">↗</span></button>
                </form>
            </div>
        </section>

        <div class="sf-search-grid mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:px-6 lg:grid-cols-[16rem_minmax(0,1fr)] lg:px-8 lg:py-14">
            <aside class="sf-search-filters self-start lg:sticky lg:top-24">
                <form action="{{ route('storefront.search') }}" method="GET" class="rounded-[1.35rem] border border-ink-200 bg-white p-5 shadow-surface">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-base font-bold text-ink-900">{{ __('storefront.search.filters_sidebar_heading') }}</h2>
                        <a href="{{ route('storefront.search') }}" class="text-xs font-bold text-secondary-600 hover:text-secondary-800">{{ __('storefront.search.clear_filters') }}</a>
                    </div>

                    <div class="mt-6 space-y-5">
                        <div>
                            <label for="product_type" class="text-xs font-bold uppercase tracking-[0.13em] text-ink-400">{{ __('storefront.search.filter_type') }}</label>
                            <select id="product_type" name="product_type" class="sf-field mt-2 w-full rounded-xl border-ink-200 bg-white text-sm">
                                <option value="">{{ __('storefront.search.type_all') }}</option>
                                <option value="rental" @selected($selectedType === 'rental')>{{ __('storefront.search.type_rental') }}</option>
                                <option value="sale" @selected($selectedType === 'sale')>{{ __('storefront.search.type_sale') }}</option>
                                <option value="digital" @selected($selectedType === 'digital')>{{ __('storefront.search.type_digital') }}</option>
                            </select>
                        </div>

                        <div>
                            <label for="category_slug" class="text-xs font-bold uppercase tracking-[0.13em] text-ink-400">{{ __('storefront.search.filter_category') }}</label>
                            <select id="category_slug" name="category_slug" class="sf-field mt-2 w-full rounded-xl border-ink-200 bg-white text-sm">
                                <option value="">{{ __('storefront.search.filter_all') }}</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->code }}" @selected($selectedCategory === $category->code)>{{ $category->getTranslation('name', app()->getLocale(), useFallbackLocale: true) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="occasion" class="text-xs font-bold uppercase tracking-[0.13em] text-ink-400">{{ __('storefront.search.filter_occasion') }}</label>
                            <select id="occasion" name="occasion" class="sf-field mt-2 w-full rounded-xl border-ink-200 bg-white text-sm">
                                <option value="">{{ __('storefront.search.filter_all') }}</option>
                                @foreach ($occasions as $occasion)
                                    <option value="{{ $occasion->code }}" @selected($selectedOccasion === $occasion->code)>{{ $occasion->getTranslation('name', app()->getLocale(), useFallbackLocale: true) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="city_public_id" class="text-xs font-bold uppercase tracking-[0.13em] text-ink-400">{{ __('storefront.search.filter_city') }}</label>
                            <select id="city_public_id" name="city_public_id" class="sf-field mt-2 w-full rounded-xl border-ink-200 bg-white text-sm">
                                <option value="">{{ __('storefront.search.filter_all_cities') }}</option>
                                @foreach ($cities as $city)
                                    <option value="{{ $city->public_id }}" @selected($selectedCity === $city->public_id)>{{ $city->getTranslation('name', app()->getLocale(), useFallbackLocale: true) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="event_date" class="text-xs font-bold uppercase tracking-[0.13em] text-ink-400">{{ __('storefront.search.filter_date') }}</label>
                            <input id="event_date" name="event_date" value="{{ $selectedDate }}" type="date" min="{{ now('UTC')->toDateString() }}" class="sf-field mt-2 w-full rounded-xl border-ink-200 bg-white text-sm">
                        </div>

                        <div>
                            <label for="vendor" class="text-xs font-bold uppercase tracking-[0.13em] text-ink-400">{{ __('storefront.search.filter_vendor') }}</label>
                            <select id="vendor" name="vendor" class="sf-field mt-2 w-full rounded-xl border-ink-200 bg-white text-sm">
                                <option value="">{{ __('storefront.search.filter_vendor_all') }}</option>
                                @foreach ($vendors as $vendor)
                                    <option value="{{ $vendor->public_id }}" @selected($selectedVendor === $vendor->public_id)>
                                        {{ $vendor->getTranslation('business_name', app()->getLocale(), useFallbackLocale: true) }}
                                        @if ($vendor->primaryCity)
                                            · {{ $vendor->primaryCity->getTranslation('name', app()->getLocale(), useFallbackLocale: true) }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.13em] text-ink-400">{{ __('storefront.search.filter_price') }}</p>
                            <div class="mt-2 grid grid-cols-2 gap-2">
                                <input name="price_min" value="{{ $priceMin }}" type="number" min="0" inputmode="numeric" placeholder="{{ __('storefront.search.filter_price_min') }}" class="sf-field min-w-0 rounded-xl border-ink-200 text-sm">
                                <input name="price_max" value="{{ $priceMax }}" type="number" min="0" inputmode="numeric" placeholder="{{ __('storefront.search.filter_price_max') }}" class="sf-field min-w-0 rounded-xl border-ink-200 text-sm">
                            </div>
                        </div>

                        <div>
                            <label for="sort" class="text-xs font-bold uppercase tracking-[0.13em] text-ink-400">{{ __('storefront.search.sort.label') }}</label>
                            <select id="sort" name="sort" class="sf-field mt-2 w-full rounded-xl border-ink-200 bg-white text-sm">
                                <option value="">{{ __('storefront.search.sort_relevance') }}</option>
                                <option value="price_asc" @selected(($filters['sort'] ?? null) === 'price_asc')>{{ __('storefront.search.sort.price_asc') }}</option>
                                <option value="price_desc" @selected(($filters['sort'] ?? null) === 'price_desc')>{{ __('storefront.search.sort.price_desc') }}</option>
                                <option value="rating_desc" @selected(($filters['sort'] ?? null) === 'rating_desc')>{{ __('storefront.search.sort.rating_desc') }}</option>
                                <option value="newest" @selected(($filters['sort'] ?? null) === 'newest')>{{ __('storefront.search.sort_newest') }}</option>
                            </select>
                        </div>
                    </div>

                    @if (! empty($filters['q']))
                        <input type="hidden" name="q" value="{{ $filters['q'] }}">
                    @endif
                    <button type="submit" class="sf-button sf-button--secondary mt-6 w-full">{{ __('storefront.search.apply_filters') }}</button>
                </form>
            </aside>

            <section class="sf-search-results" aria-labelledby="search-results-heading">
                <div class="flex flex-col justify-between gap-4 border-b border-ink-200 pb-5 sm:flex-row sm:items-end">
                    <div>
                        <p class="text-sm font-semibold text-ink-500">{{ trans_choice('storefront.search.results_count', $services->total(), ['count' => $services->total()]) }}</p>
                        <h2 id="search-results-heading" class="mt-1 text-2xl font-extrabold tracking-[-0.03em] text-ink-900">{{ __('storefront.search.filters_sidebar_label') }}</h2>
                    </div>
                    @if ($services->total() > 0)
                        <p class="text-sm text-ink-400">{{ __('storefront.common.pagination') }} {{ $services->currentPage() }} / {{ $services->lastPage() }}</p>
                    @endif
                </div>

                @if ($services->isEmpty())
                    <div class="mt-8 rounded-[1.5rem] border border-dashed border-ink-300 bg-white px-6 py-16 text-center">
                        <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-secondary-50 text-2xl text-secondary-700" aria-hidden="true">⌕</div>
                        <h3 class="mt-5 text-xl font-bold text-ink-900">{{ __('storefront.search.empty_title') }}</h3>
                        <p class="mx-auto mt-3 max-w-md text-sm leading-7 text-ink-500">{{ __('storefront.search.empty_body') }}</p>
                        <a href="{{ route('storefront.search') }}" class="sf-button sf-button--secondary mt-6">{{ __('storefront.search.empty_action') }}</a>
                    </div>
                @else
                    <div class="sf-catalog-grid mt-8 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($services as $service)
                            <x-storefront.service-card :service="$service" />
                        @endforeach
                    </div>

                    <nav class="mt-10 flex items-center justify-between gap-4" aria-label="{{ __('storefront.common.pagination') }}">
                        @if ($services->onFirstPage())
                            <span class="rounded-full border border-ink-200 px-4 py-2 text-sm text-ink-300">{{ __('storefront.search.page_prev') }}</span>
                        @else
                            <a href="{{ $services->previousPageUrl() }}" class="rounded-full border border-ink-300 px-4 py-2 text-sm font-semibold text-ink-700 hover:border-secondary-400 hover:text-secondary-700">{{ __('storefront.search.page_prev') }}</a>
                        @endif
                        <span class="text-sm font-semibold text-ink-500">{{ $services->currentPage() }} / {{ $services->lastPage() }}</span>
                        @if ($services->hasMorePages())
                            <a href="{{ $services->nextPageUrl() }}" class="rounded-full border border-ink-300 px-4 py-2 text-sm font-semibold text-ink-700 hover:border-secondary-400 hover:text-secondary-700">{{ __('storefront.search.page_next') }}</a>
                        @else
                            <span class="rounded-full border border-ink-200 px-4 py-2 text-sm text-ink-300">{{ __('storefront.search.page_next') }}</span>
                        @endif
                    </nav>
                @endif
            </section>
        </div>
    </div>
@endsection
