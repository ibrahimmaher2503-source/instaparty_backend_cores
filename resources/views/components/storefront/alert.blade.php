@props(['type' => 'info'])

<div
    role="{{ $type === 'error' ? 'alert' : 'status' }}"
    {{ $attributes->class([
        'rounded-md px-4 py-3 text-sm',
        'bg-gray-100 text-gray-800' => $type === 'info',
        'bg-green-50 text-green-800' => $type === 'success',
        'bg-red-50 text-red-800' => $type === 'error',
    ]) }}
>
    {{ $slot }}
</div>
