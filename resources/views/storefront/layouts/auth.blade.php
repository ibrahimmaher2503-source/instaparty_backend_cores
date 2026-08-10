@extends('storefront.layouts.app')

@section('content')
    <div class="mx-auto flex max-w-md flex-col gap-6 px-4 py-12">
        <header class="space-y-1">
            <h1 class="text-2xl font-semibold text-gray-900">@yield('heading')</h1>

            @hasSection('subheading')
                <p class="text-sm text-gray-600">@yield('subheading')</p>
            @endif
        </header>

        {{-- Flash status (password reset sent, OTP resent, ...) --}}
        @if (session('status'))
            <x-storefront.alert type="success">{{ session('status') }}</x-storefront.alert>
        @endif

        {{-- Errors not tied to a specific field, e.g. failed credentials --}}
        @if ($errors->has('login'))
            <x-storefront.alert type="error">{{ $errors->first('login') }}</x-storefront.alert>
        @endif

        @yield('form')
    </div>
@endsection
