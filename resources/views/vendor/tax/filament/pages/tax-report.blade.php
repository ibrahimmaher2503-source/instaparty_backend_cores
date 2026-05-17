<x-filament-panels::page>
    <form wire:submit.prevent="apply">
        {{ $this->form }}
        <div class="mt-4">
            <x-filament::button type="submit">
                {{ __('tax.apply_filter') }}
            </x-filament::button>
        </div>
    </form>

    @php $stats = $this->getStats(); @endphp

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
        <x-filament::card>
            <div class="text-sm text-gray-500">{{ __('tax.total_vat_collected') }}</div>
            <div class="text-2xl font-bold mt-1">{{ number_format($stats['total_vat'] / 100, 2) }} EGP</div>
        </x-filament::card>
        <x-filament::card>
            <div class="text-sm text-gray-500">{{ __('tax.taxable_bookings') }}</div>
            <div class="text-2xl font-bold mt-1">{{ number_format($stats['total_bookings']) }}</div>
        </x-filament::card>
        <x-filament::card>
            <div class="text-sm text-gray-500">{{ __('tax.avg_vat_per_booking') }}</div>
            <div class="text-2xl font-bold mt-1">{{ number_format($stats['avg_vat'] / 100, 2) }} EGP</div>
        </x-filament::card>
    </div>
</x-filament-panels::page>
