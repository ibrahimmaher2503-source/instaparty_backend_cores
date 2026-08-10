@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'autocomplete' => null,
])

@php
    // Never repopulate a password field from old input.
    $isSecret = $type === 'password';
    $resolved = $isSecret ? '' : old($name, $value);

    $hasError = $errors->has($name);
    $hintId = $hint ? $name.'-hint' : null;
    $errorId = $hasError ? $name.'-error' : null;
    $describedBy = collect([$hintId, $errorId])->filter()->implode(' ');
@endphp

<div class="space-y-1.5">
    <label for="{{ $name }}" class="block text-sm font-medium text-ink-900">
        {{ $label }}
    </label>

    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ $resolved }}"
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        @if ($hasError) aria-invalid="true" @endif
        {{ $attributes->class([
            // sf-field carries the focus treatment: primary border plus a 25% ring.
            'sf-field block w-full rounded-md border bg-white px-3 py-2 text-sm text-ink-900',
            'placeholder:text-ink-400 disabled:cursor-not-allowed disabled:bg-ink-50 disabled:text-ink-400',
            'border-ink-300' => ! $hasError,
            'border-danger' => $hasError,
        ]) }}
    >

    @if ($hint)
        <p id="{{ $hintId }}" class="text-xs text-ink-500">{{ $hint }}</p>
    @endif

    @error($name)
        {{--
            Never colour alone: the error carries an icon and text as well as the red
            border, so it survives colour-blindness and greyscale printing.
            PRODUCT.md requires this explicitly for form errors.
        --}}
        <p id="{{ $errorId }}" class="flex items-start gap-1.5 text-xs text-danger">
            <svg class="mt-0.5 size-3.5 shrink-0" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                <path d="M8 1.5a6.5 6.5 0 1 0 0 13 6.5 6.5 0 0 0 0-13ZM7.25 4.5h1.5v4.25h-1.5V4.5ZM8 12a.9.9 0 1 1 0-1.8A.9.9 0 0 1 8 12Z" />
            </svg>
            <span>{{ $message }}</span>
        </p>
    @enderror
</div>
