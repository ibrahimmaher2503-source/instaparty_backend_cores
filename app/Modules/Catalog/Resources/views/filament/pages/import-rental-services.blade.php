<x-filament-panels::page>
    <form wire:submit="import">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button type="submit">
                {{ __('catalog.import_button') }}
            </x-filament::button>
        </div>
    </form>

    @if ($this->lastImport)
        @if ($this->lastImport->status === 'completed')
            <x-filament::section class="mt-6">
                <p class="text-success-600 font-medium">
                    {{ __('catalog.imported_rows', ['count' => $this->lastImport->imported_rows]) }}
                </p>
            </x-filament::section>
        @else
            <x-filament::section class="mt-6">
                <p class="text-danger-600 font-medium mb-3">
                    {{ __('catalog.import_failed_rows', ['count' => $this->lastImport->error_rows]) }}
                </p>
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2 pr-4">{{ __('catalog.row') }}</th>
                            <th class="text-left py-2 pr-4">{{ __('catalog.field') }}</th>
                            <th class="text-left py-2">{{ __('catalog.error') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->lastImport->errors as $error)
                            <tr class="border-b">
                                <td class="py-2 pr-4">{{ $error->row_number }}</td>
                                <td class="py-2 pr-4">{{ $error->field }}</td>
                                <td class="py-2">{{ $error->message[app()->getLocale()] ?? $error->message['en'] ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
