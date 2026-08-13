@extends('storefront.layouts.app')

@php
    use App\Modules\Catalog\Application\Actions\ListPublicCategoriesAction;
    use App\Modules\Catalog\Application\Actions\ListPublicOccasionsAction;
    use App\Modules\Catalog\Application\Actions\ListPublishedServicesAction;

    $blocks = collect($homepage['blocks'] ?? []);
    $heroBlock = $blocks->first(
        static fn (array $block): bool => $block['block_type'] === 'hero_carousel',
    );
    $vendorJoinBlock = $blocks->first(
        static fn (array $block): bool => $block['block_type'] === 'vendor_join',
    );

    $featuredServiceIds = $blocks
        ->where('block_type', 'featured_services')
        ->flatMap(static fn (array $block): array => (array) data_get($block, 'payload.service_public_ids', []))
        ->unique()
        ->values()
        ->all();

    $featuredServices = app(ListPublishedServicesAction::class)->execute($featuredServiceIds);
    $categories = app(ListPublicCategoriesAction::class)->execute();
    $occasions = app(ListPublicOccasionsAction::class)->execute();
    $featuredVendors = app(App\Modules\Discovery\Infrastructure\Repositories\VendorBrowsingRepository::class)
        ->featuredForStorefront();
    $packages = collect($homepage['packages'] ?? []);
    $packagesRendered = false;
@endphp

@section('title', __('storefront.common.site_name').' · '.__('storefront.home.hero.headline'))

@section('content')
    @if ($heroBlock)
        <x-home.hero :block="$heroBlock" :fallback-image-url="$homepage['hero_image_url'] ?? null" />
    @else
        <x-home.hero :block="['payload' => ['slides' => [[
            'eyebrow' => __('storefront.home.hero.eyebrow'),
            'headline' => __('storefront.home.hero.headline'),
            'sub' => __('storefront.home.hero.sub'),
            'cta_label' => __('storefront.home.hero.primary_cta'),
            'cta_url' => '/wizard',
        ]]]]" :fallback-image-url="asset('images/home-hero/cake.png')" />
    @endif

    <div class="sf-home-flow">
        <x-home.party-builder :occasions="$occasions" :cities="$cities" />

        @foreach ($blocks->reject(static fn (array $block): bool => in_array($block['block_type'], ['hero_carousel', 'vendor_join'], true)) as $block)
            <x-home.block
                :block="$block"
                :services="$featuredServices"
                :categories="$categories"
                :occasions="$occasions"
            />

            @if (! $packagesRendered && $block['block_type'] === 'featured_services' && $packages->isNotEmpty())
                <x-home.custom-packages :packages="$packages" />
                @php($packagesRendered = true)
            @endif
        @endforeach

        @if (! $packagesRendered && $packages->isNotEmpty())
            <x-home.custom-packages :packages="$packages" />
        @endif

        @if (! $blocks->contains('block_type', 'featured_categories') && $categories->isNotEmpty())
            <x-home.taxonomy
                :block="['public_id' => 'fallback-categories', 'payload' => []]"
                :items="$categories"
                kind="category"
            />
        @endif

        <x-home.featured-vendors :vendors="$featuredVendors" />
        <x-home.how-it-works />

        @if ($vendorJoinBlock)
            <x-home.vendor-join :block="$vendorJoinBlock" />
        @else
            <x-home.vendor-join :block="['public_id' => 'fallback-vendor-join', 'payload' => []]" />
        @endif
    </div>
@endsection
