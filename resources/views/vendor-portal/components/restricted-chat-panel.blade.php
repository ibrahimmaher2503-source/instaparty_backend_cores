<div class="mt-6 rounded-lg border bg-white dark:bg-gray-900 shadow-sm">

    {{-- Panel header --}}
    <div class="px-4 py-3 border-b flex items-center gap-2">
        <x-heroicon-o-chat-bubble-left-right class="h-5 w-5 text-primary-600" />
        <span class="font-semibold text-sm">{{ __('communication::chat.panel_title') }}</span>
    </div>

    {{-- STATE: Placeholder — booking not yet confirmed, no thread needed --}}
    @if($this->state === \App\Modules\Communication\Domain\Enums\ChatPanelState::Placeholder)
        <div class="p-6 text-center text-gray-400 text-sm">
            {{ __('communication::chat.placeholder_notice') }}
        </div>

    {{-- STATE: Frozen — admin has suspended the thread; show banner + audit log, no compose --}}
    @elseif($this->state === \App\Modules\Communication\Domain\Enums\ChatPanelState::Frozen)
        <div class="bg-warning-50 border-b border-warning-200 px-4 py-3">
            <p class="text-sm font-semibold text-warning-800">{{ __('communication::chat.frozen_banner_title') }}</p>
            <p class="text-xs text-warning-700 mt-0.5">{{ __('communication::chat.frozen_banner_body') }}</p>
            @if($this->thread?->frozenByUser)
                <p class="text-xs text-warning-600 mt-1">
                    {{ __('communication::chat.frozen_by', ['name' => $this->thread->frozenByUser->name]) }}
                </p>
            @endif
        </div>
        @include('vendor-portal.components.chat-messages-list')

    {{-- STATE: Closed — booking lifecycle is over; show audit log but no compose --}}
    @elseif($this->state === \App\Modules\Communication\Domain\Enums\ChatPanelState::Closed)
        @include('vendor-portal.components.chat-messages-list')
        <div class="px-4 py-3 border-t text-center text-sm text-gray-400">
            {{ __('communication::chat.closed_notice') }}
        </div>

    {{-- STATE: SystemLocked — platform-level lock (e.g. ongoing dispute) --}}
    @elseif($this->state === \App\Modules\Communication\Domain\Enums\ChatPanelState::SystemLocked)
        <div class="p-6 text-center text-gray-400 text-sm">
            {{ __('communication::chat.system_locked_notice') }}
        </div>

    {{-- STATE: Open — show audit log + compose form --}}
    @else
        @include('vendor-portal.components.chat-messages-list')

        {{-- Flash feedback (blocked or sent) --}}
        @if($flashMessage)
            <div @class([
                'px-4 py-2 text-sm border-t',
                'text-danger-600 bg-danger-50' => $isBlocked,
                'text-success-600 bg-success-50' => !$isBlocked,
            ])>
                {{ $flashMessage }}
            </div>
        @endif

        {{-- Compose form --}}
        <form wire:submit="sendMessage" class="px-4 py-3 border-t flex gap-2 items-end">
            <textarea
                wire:model="body"
                placeholder="{{ __('communication::chat.compose_placeholder') }}"
                rows="2"
                class="flex-1 resize-none rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-100"
            ></textarea>
            <button
                type="submit"
                wire:loading.attr="disabled"
                class="self-end rounded-md bg-primary-600 px-4 py-2 text-sm text-white hover:bg-primary-700 disabled:opacity-50 transition-opacity"
            >
                <span wire:loading.remove>{{ __('communication::chat.send_button') }}</span>
                <span wire:loading>{{ __('communication::chat.sending') }}</span>
            </button>
        </form>
    @endif

</div>
