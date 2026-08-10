@extends('storefront.layouts.auth')

@section('title', __('storefront.auth.login.title'))
@section('heading', __('storefront.auth.login.title'))
@section('subheading', __('storefront.auth.login.subtitle'))

@section('form')
    <form method="POST" action="{{ route('storefront.auth.login') }}" class="space-y-4">
        @csrf

        <x-storefront.input
            name="login"
            :label="__('storefront.auth.fields.login_identifier')"
            autocomplete="username"
        />

        <x-storefront.input
            name="password"
            type="password"
            :label="__('storefront.auth.fields.password')"
            autocomplete="current-password"
        />

        <label class="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" name="remember" value="1" class="rounded border-gray-300">
            {{ __('storefront.common.remember_me') }}
        </label>

        <button type="submit" class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">
            {{ __('storefront.auth.login.submit') }}
        </button>
    </form>

    <div class="flex flex-col gap-2 text-sm">
        <a href="{{ route('storefront.auth.forgot') }}" class="text-gray-700 underline">
            {{ __('storefront.auth.login.forgot') }}
        </a>

        <p class="text-gray-600">
            {{ __('storefront.auth.login.no_account') }}
            <a href="{{ route('storefront.auth.register') }}" class="text-gray-900 underline">
                {{ __('storefront.auth.register.submit') }}
            </a>
        </p>
    </div>
@endsection
