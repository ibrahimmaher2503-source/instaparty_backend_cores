@props(['tone' => 'neutral'])

@php
    /*
    | Status and taxonomy chips.
    |
    | The three product types map to fixed tones — rental/warning, sale/success,
    | digital/info — and callers pass the tone from a match() on the ProductType
    | enum, never from a string comparison on the type name.
    */
    $tones = [
        'neutral' => 'bg-ink-100 text-ink-700',
        'brand' => 'bg-primary-50 text-primary-500',
        'success' => 'bg-white text-success ring-1 ring-success/25',
        'warning' => 'bg-accent-50 text-accent-500',
        'danger' => 'bg-white text-danger ring-1 ring-danger/25',
        'info' => 'bg-secondary-50 text-secondary-500',
    ];
@endphp

<span {{ $attributes->class(['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', $tones[$tone] ?? $tones['neutral']]) }}>
    {{ $slot }}
</span>
