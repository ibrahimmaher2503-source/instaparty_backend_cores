<div
    class="h-64 overflow-y-auto px-4 py-3 space-y-2"
    x-data
    x-init="$el.scrollTop = $el.scrollHeight"
>
    @forelse($this->messages as $message)
        @php $isSelf = $message->sender_id === auth()->id(); @endphp

        <div @class(['flex items-end gap-2', 'justify-end' => $isSelf])>
            <div @class([
                'rounded-lg px-3 py-2 text-xs max-w-xs',
                'bg-primary-600 text-white'                                        => $isSelf && !$message->flagged,
                'bg-red-100 text-red-700 border border-red-300'                    => $message->flagged,
                'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200'   => !$isSelf && !$message->flagged,
            ])>
                <span class="font-medium">
                    {{ $isSelf ? __('communication::chat.you') : __('communication::chat.customer') }}
                </span>

                @if($message->flagged)
                    — <em>{{ __('communication::chat.message_blocked') }}</em>
                @endif

                <span class="block mt-0.5 opacity-60 text-[10px]">
                    {{ $message->created_at->format('H:i') }}
                </span>
            </div>
        </div>
    @empty
        <p class="text-center text-xs text-gray-400 py-4">—</p>
    @endforelse
</div>
