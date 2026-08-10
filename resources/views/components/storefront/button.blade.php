@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'loading' => false,
])

@php
    /*
    | The system's single button vocabulary. Same shape everywhere: 8px radius,
    | never pill, never square. Primary is the only Celebration Rose surface on a
    | page (the One-Rose Rule), so a screen with two primary buttons has a bug in it.
    |
    | Renders as <a> when given href and <button> otherwise, so a link that looks
    | like a button is still a link — right-click, middle-click and copy-address all
    | keep working, and the accessible role stays honest.
    */
    $base = 'inline-flex items-center justify-center gap-2 rounded-md font-medium '
        .'transition-colors duration-150 '
        .'disabled:cursor-not-allowed disabled:opacity-60';

    $variants = [
        // sf-primary-hover derives the darker rose with color-mix, so it stays
        // correct under an admin-rethemed palette (the token ramp is flat).
        'primary' => 'bg-primary-500 text-white shadow-surface sf-primary-hover',
        'secondary' => 'bg-white text-ink-900 border border-ink-200 shadow-surface hover:bg-ink-50',
        'ghost' => 'text-ink-500 hover:bg-ink-100 hover:text-ink-900',
    ];

    $sizes = [
        'sm' => 'px-3 py-1.5 text-sm',
        'md' => 'px-4 py-2 text-sm',
        'lg' => 'px-5 py-2.5 text-base',
    ];

    $classes = trim($base.' '.($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']));
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        {{ $slot }}
    </a>
@else
    <button
        type="{{ $attributes->get('type', 'submit') }}"
        @disabled($loading || $attributes->get('disabled'))
        {{ $attributes->except(['type', 'disabled'])->class($classes) }}
    >
        @if ($loading)
            {{-- aria-hidden: the loading state is announced by the button's own
                 disabled state and label, not by the decorative spinner. --}}
            <svg class="size-4 animate-spin" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <circle cx="8" cy="8" r="6" stroke="currentColor" stroke-opacity="0.25" stroke-width="2" />
                <path d="M14 8a6 6 0 0 0-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
        @endif

        {{ $slot }}
    </button>
@endif
