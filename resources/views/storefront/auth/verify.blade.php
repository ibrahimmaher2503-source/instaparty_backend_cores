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

        <x-storefront.button class="w-full">
            {{ __('storefront.auth.verify.submit') }}
        </x-storefront.button>
    </form>

    <form method="POST" action="{{ route('storefront.auth.verify.send') }}">
        @csrf
        <button type="submit" class="text-sm font-medium text-ink-500 underline underline-offset-2 hover:text-ink-900">
            {{ __('storefront.auth.verify.resend') }}
        </button>
    </form>
@endsection
