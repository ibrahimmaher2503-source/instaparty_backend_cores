@extends('storefront.layouts.app')

@section('content')
    {{--
        Phase 0 shell. Phase 1 replaces the body with the CMS-driven block renderer
        (hero, featured services, vendor spotlight, ...) backed by the same action
        behind the /cms/homepage endpoint. Kept intentionally sparse rather than
        filled with placeholder cards, so nobody mistakes scaffolding for design.
    --}}
    <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6">
        <div class="max-w-2xl space-y-4">
            <h1 class="text-4xl font-bold leading-tight tracking-tight text-ink-900 sm:text-5xl">
                {{ __('storefront.home.hero_default_headline') }}
            </h1>

            <p class="max-w-prose text-lg leading-normal text-ink-500">
                {{ __('storefront.home.hero_default_sub') }}
            </p>
        </div>
    </section>
@endsection
