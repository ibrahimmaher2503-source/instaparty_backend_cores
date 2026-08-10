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

        <label class="flex items-center gap-2 text-sm text-ink-700">
            <input type="checkbox" name="remember" value="1" class="rounded border-ink-300 text-primary-500">
            {{ __('storefront.common.remember_me') }}
        </label>

        <x-storefront.button class="w-full">
            {{ __('storefront.auth.login.submit') }}
        </x-storefront.button>
    </form>

    <div class="flex flex-col gap-2 text-sm">
        <a href="{{ route('storefront.auth.forgot') }}" class="font-medium text-ink-500 underline underline-offset-2 hover:text-ink-900">
            {{ __('storefront.auth.login.forgot') }}
        </a>

        <p class="text-gray-600">
            {{ __('storefront.auth.login.no_account') }}
            <a href="{{ route('storefront.auth.register') }}" class="font-medium text-primary-500 underline underline-offset-2">
                {{ __('storefront.auth.register.submit') }}
            </a>
        </p>
    </div>
@endsection
