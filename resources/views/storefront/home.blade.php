@extends('storefront.layouts.app')

@section('content')
    {{--
        Phase 0 placeholder. Phase 1 replaces this with the CMS-driven block
        renderer (hero carousel, featured services, vendor spotlight, ...) backed
        by the same action behind the /cms/homepage endpoint.
    --}}
    <div class="mx-auto max-w-7xl px-4 py-16">
        <h1 class="text-3xl font-semibold">{{ config('app.name') }}</h1>
    </div>
@endsection
