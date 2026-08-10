@extends('storefront.layouts.auth')

@section('title', __('storefront.auth.forgot.title'))
@section('heading', __('storefront.auth.forgot.title'))
@section('subheading', __('storefront.auth.forgot.description'))

@section('form')
    <form method="POST" action="{{ route('storefront.auth.forgot') }}" class="space-y-4">
        @csrf

        <x-storefront.input
            name="identifier"
            :label="__('storefront.auth.forgot.identifier_label')"
            :placeholder="__('storefront.auth.forgot.identifier_placeholder')"
            autocomplete="username"
            dir="ltr"
        />

        <button type="submit" class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">
            {{ __('storefront.auth.forgot.submit') }}
        </button>
    </form>

    <a href="{{ route('storefront.auth.login') }}" class="text-sm text-gray-700 underline">
        {{ __('storefront.auth.forgot.back_to_login') }}
    </a>
@endsection
