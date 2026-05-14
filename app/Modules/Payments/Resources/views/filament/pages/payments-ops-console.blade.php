<x-filament-panels::page>
    <div class="space-y-4">
        <div class="flex flex-wrap gap-2">
            @foreach($this->tabs() as $key => $label)
                <button
                    wire:click="setTab('{{ $key }}')"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition-colors
                        {{ $activeTab === $key
                            ? 'bg-primary-600 text-white'
                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
