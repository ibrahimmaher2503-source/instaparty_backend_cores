@extends('storefront.layouts.auth')

@section('title', __('storefront.auth.verify.title'))
@section('heading', __('storefront.auth.verify.title'))
@section('subheading', __('storefront.auth.verify.subtitle', ['phone' => $phone]))

@section('form')
    <form method="POST" action="{{ route('storefront.auth.verify') }}" class="space-y-4">
        @csrf

        <x-storefront.input
            name="code"
            :label="__('storefront.auth.fields.otp_code')"
            inputmode="numeric"
            autocomplete="one-time-code"
            maxlength="6"
            dir="ltr"
        />

        <button type="submit" class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">
            {{ __('storefront.auth.verify.submit') }}
        </button>
    </form>

    <form method="POST" action="{{ route('storefront.auth.verify.send') }}">
        @csrf
        <button type="submit" class="text-sm text-gray-700 underline">
            {{ __('storefront.auth.verify.resend') }}
        </button>
    </form>
@endsection
