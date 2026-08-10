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
    $isSecret = in_array($type, ['password'], true);
    $resolved = $isSecret ? '' : old($name, $value);
    $hintId = $hint ? $name.'-hint' : null;
    $errorId = $errors->has($name) ? $name.'-error' : null;
    $describedBy = collect([$hintId, $errorId])->filter()->implode(' ');
@endphp

<div class="space-y-1">
    <label for="{{ $name }}" class="block text-sm font-medium text-gray-900">
        {{ $label }}
    </label>

    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ $resolved }}"
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        @if ($errors->has($name)) aria-invalid="true" @endif
        {{ $attributes->class([
            'block w-full rounded-md border px-3 py-2 text-sm',
            'border-gray-300 focus:border-gray-900 focus:ring-gray-900' => ! $errors->has($name),
            'border-red-500 focus:border-red-600 focus:ring-red-600' => $errors->has($name),
        ]) }}
    >

    @if ($hint)
        <p id="{{ $hintId }}" class="text-xs text-gray-500">{{ $hint }}</p>
    @endif

    @error($name)
        <p id="{{ $errorId }}" class="text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
