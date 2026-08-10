@extends('storefront.layouts.auth')

@section('title', __('storefront.auth.register.title'))
@section('heading', __('storefront.auth.register.title'))
@section('subheading', __('storefront.auth.register.subtitle'))

@section('form')
    <form method="POST" action="{{ route('storefront.auth.register') }}" class="space-y-4">
        @csrf

        <x-storefront.input
            name="name"
            :label="__('storefront.auth.fields.name')"
            autocomplete="name"
        />

        <x-storefront.input
            name="phone_e164"
            type="tel"
            :label="__('storefront.auth.fields.phone')"
            :hint="__('storefront.auth.fields.phone_hint')"
            :placeholder="__('storefront.auth.fields.phone_placeholder')"
            autocomplete="tel"
            dir="ltr"
        />

        <x-storefront.input
            name="email"
            type="email"
            :label="__('storefront.auth.fields.email_optional')"
            autocomplete="email"
            dir="ltr"
        />

        <x-storefront.input
            name="password"
            type="password"
            :label="__('storefront.auth.fields.password')"
            autocomplete="new-password"
        />

        <x-storefront.input
            name="password_confirmation"
            type="password"
            :label="__('storefront.auth.fields.password_confirmation')"
            autocomplete="new-password"
        />

        <div class="space-y-1">
            <label class="flex items-start gap-2 text-sm text-ink-700">
                <input type="checkbox" name="accepted_terms" value="1" class="mt-1 rounded border-ink-300 text-primary-500" @checked(old('accepted_terms'))>
                <span>
                    {{ __('storefront.auth.register.tc_agree') }}
                    <a href="{{ route('storefront.home') }}" class="underline">{{ __('storefront.auth.register.tc_link') }}</a>
                </span>
            </label>

            @error('accepted_terms')
                <p class="text-xs text-danger">{{ $message }}</p>
            @enderror
        </div>

        {{-- The API defaults preferred_locale from the payload; carry the URL locale. --}}
        <input type="hidden" name="preferred_locale" value="{{ app()->getLocale() }}">

        <x-storefront.button class="w-full">
            {{ __('storefront.auth.register.submit') }}
        </x-storefront.button>
    </form>

    <p class="text-sm text-ink-500">
        {{ __('storefront.auth.register.have_account') }}
        <a href="{{ route('storefront.auth.login') }}" class="font-medium text-primary-500 underline underline-offset-2">
            {{ __('storefront.auth.login.submit') }}
        </a>
    </p>
@endsection
