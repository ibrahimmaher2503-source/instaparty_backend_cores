<x-filament-panels::page>
    <x-filament-panels::form wire:submit="saveSettings">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="[
                \Filament\Actions\Action::make('save')
                    ->label('Save Settings')
                    ->submit('saveSettings'),
            ]"
        />
    </x-filament-panels::form>

    <div class="mt-8">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
