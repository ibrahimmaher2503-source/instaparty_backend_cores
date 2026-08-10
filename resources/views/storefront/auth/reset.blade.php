@extends('storefront.layouts.auth')

@section('title', __('storefront.auth.reset.title'))
@section('heading', __('storefront.auth.reset.title'))
@section('subheading', __('storefront.auth.reset.subtitle'))

@section('form')
    <form method="POST" action="{{ route('storefront.auth.reset') }}" class="space-y-4">
        @csrf

        <x-storefront.input
            name="identifier"
            :label="__('storefront.auth.reset.identifier_label')"
            :value="$identifier"
            autocomplete="username"
            dir="ltr"
        />

        <input type="hidden" name="token" value="{{ $token }}">

        @error('token')
            <x-storefront.alert type="error">{{ __('storefront.auth.reset.invalid_token') }}</x-storefront.alert>
        @enderror

        <x-storefront.input
            name="password"
            type="password"
            :label="__('storefront.auth.reset.password_label')"
            autocomplete="new-password"
        />

        <x-storefront.input
            name="password_confirmation"
            type="password"
            :label="__('storefront.auth.reset.confirm_label')"
            autocomplete="new-password"
        />

        <button type="submit" class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">
            {{ __('storefront.auth.reset.submit') }}
        </button>
    </form>

    <a href="{{ route('storefront.auth.forgot') }}" class="text-sm text-gray-700 underline">
        {{ __('storefront.auth.reset.retry_link') }}
    </a>
@endsection
