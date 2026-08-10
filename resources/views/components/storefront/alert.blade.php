@props(['type' => 'info'])

@php
    /*
    | Status messaging. Background tint plus an icon — never a coloured side-stripe,
    | which is a banned pattern, and never colour alone, which PRODUCT.md rules out
    | for status communication.
    */
    $styles = [
        'info' => 'bg-secondary-50 text-secondary-500',
        'success' => 'bg-primary-50 text-success',
        'warning' => 'bg-accent-50 text-accent-500',
        'error' => 'bg-white text-danger border border-danger/30',
    ];

    $icons = [
        'info' => 'M8 1.5a6.5 6.5 0 1 0 0 13 6.5 6.5 0 0 0 0-13ZM7.25 6.75h1.5V11.5h-1.5V6.75ZM8 5.8a.9.9 0 1 1 0-1.8.9.9 0 0 1 0 1.8Z',
        'success' => 'M8 1.5a6.5 6.5 0 1 0 0 13 6.5 6.5 0 0 0 0-13Zm3.03 4.72-3.75 3.75a.75.75 0 0 1-1.06 0L4.97 8.72l1.06-1.06 1.22 1.22 3.22-3.22 1.06 1.06Z',
        'warning' => 'M7.4 2.1a.7.7 0 0 1 1.2 0l5.3 9.6a.7.7 0 0 1-.6 1.05H2.7a.7.7 0 0 1-.6-1.05L7.4 2.1ZM7.25 6v3.25h1.5V6h-1.5Zm.75 5.7a.85.85 0 1 0 0-1.7.85.85 0 0 0 0 1.7Z',
        'error' => 'M8 1.5a6.5 6.5 0 1 0 0 13 6.5 6.5 0 0 0 0-13ZM7.25 4.5h1.5v4.25h-1.5V4.5ZM8 12a.9.9 0 1 1 0-1.8A.9.9 0 0 1 8 12Z',
    ];
@endphp

<div
    role="{{ $type === 'error' ? 'alert' : 'status' }}"
    {{ $attributes->class(['flex items-start gap-2.5 rounded-md px-4 py-3 text-sm', $styles[$type] ?? $styles['info']]) }}
>
    <svg class="mt-0.5 size-4 shrink-0" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
        <path d="{{ $icons[$type] ?? $icons['info'] }}" />
    </svg>

    <div>{{ $slot }}</div>
</div>
