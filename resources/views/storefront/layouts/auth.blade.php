@extends('storefront.layouts.app')

@section('content')
    {{--
        Narrow, centred, generous top space. Auth is a task with one job on screen, so
        the column is tighter than the browse pages and the rhythm is deliberately
        uneven: a wide gap under the heading, tighter gaps between form and helpers.
    --}}
    <div class="mx-auto w-full max-w-md px-4 py-14 sm:px-0">
        <header class="mb-8 space-y-2">
            <h1 class="text-2xl font-bold tracking-tight text-ink-900">@yield('heading')</h1>

            @hasSection('subheading')
                <p class="text-sm leading-normal text-ink-500">@yield('subheading')</p>
            @endif
        </header>

        <div class="space-y-4">
            {{-- Flash status: reset link sent, OTP resent, password updated. --}}
            @if (session('status'))
                <x-storefront.alert type="success">{{ session('status') }}</x-storefront.alert>
            @endif

            {{-- Errors with no field of their own, e.g. rejected credentials. --}}
            @if ($errors->has('login'))
                <x-storefront.alert type="error">{{ $errors->first('login') }}</x-storefront.alert>
            @endif
        </div>

        <div class="mt-6 space-y-6">
            @yield('form')
        </div>
    </div>
@endsection
