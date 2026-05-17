<x-filament-panels::page>
    {{ $this->infolist }}

    @livewire(
        'communication.vendor.restricted-chat-panel',
        ['bookingPublicId' => $this->record->public_id],
        key('chat-' . $this->record->public_id)
    )
</x-filament-panels::page>
