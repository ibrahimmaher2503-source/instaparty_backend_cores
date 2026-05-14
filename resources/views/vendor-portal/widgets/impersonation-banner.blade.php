@if($isImpersonating)
    <div class="fi-wi-impersonation-banner rounded-xl bg-danger-50 border border-danger-200 px-4 py-3 dark:bg-danger-950 dark:border-danger-800">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-3">
                <x-heroicon-o-shield-exclamation class="h-5 w-5 text-danger-600 dark:text-danger-400 flex-shrink-0" />
                <div>
                    <p class="text-sm font-semibold text-danger-800 dark:text-danger-200">
                        {{ __('identity.vendor_portal.impersonation_banner') }}
                    </p>
                    <p class="text-xs text-danger-600 dark:text-danger-400">
                        {{ $adminName }}
                        @if($startedAt)
                            &nbsp;·&nbsp;{{ $startedAt }}
                        @endif
                    </p>
                </div>
            </div>

            <x-filament::button
                color="danger"
                size="sm"
                wire:click="endImpersonation"
                wire:loading.attr="disabled"
            >
                {{ __('identity.vendor_portal.impersonation_end') }}
            </x-filament::button>
        </div>
    </div>
@endif
