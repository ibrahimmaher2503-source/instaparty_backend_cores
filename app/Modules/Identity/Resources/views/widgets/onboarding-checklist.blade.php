<x-filament-widgets::widget>
    {{-- US3: Suspended vendor — show only the suspension banner --}}
    @if ($checklist->isSuspended)
        <x-filament::section>
            <div class="flex items-start gap-3 p-1">
                <x-heroicon-o-no-symbol class="w-6 h-6 text-danger-600 shrink-0 mt-0.5" />
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-danger-700 dark:text-danger-400">
                        {{ __('identity::vendor-onboarding.banner.suspended_heading') }}
                    </p>
                    @if ($checklist->suspendedAt)
                        <p class="text-sm text-gray-500 mt-0.5">
                            {{ __('identity::vendor-onboarding.banner.suspended_since', ['date' => $checklist->suspendedAt->translatedFormat('d M Y')]) }}
                        </p>
                    @endif
                    @if ($checklist->suspensionReason)
                        <p class="text-sm text-gray-700 dark:text-gray-300 mt-1">
                            {{ $checklist->suspensionReason }}
                        </p>
                    @endif
                </div>
            </div>
        </x-filament::section>
        @return
    @endif

    <x-filament::section>
        {{-- US2: Rejection / changes-requested banner --}}
        @if ($checklist->rejectionState->value !== 'none')
            <div class="rounded-lg p-4 mb-4 flex items-start gap-3
                {{ $checklist->rejectionState->value === 'rejected'
                    ? 'bg-danger-50 dark:bg-danger-950 border border-danger-200 dark:border-danger-800'
                    : 'bg-warning-50 dark:bg-warning-950 border border-warning-200 dark:border-warning-800' }}">
                <x-heroicon-o-exclamation-triangle class="w-5 h-5 shrink-0 mt-0.5
                    {{ $checklist->rejectionState->value === 'rejected'
                        ? 'text-danger-600'
                        : 'text-warning-600' }}" />
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-sm
                        {{ $checklist->rejectionState->value === 'rejected'
                            ? 'text-danger-800 dark:text-danger-200'
                            : 'text-warning-800 dark:text-warning-200' }}">
                        {{ $checklist->rejectionState->value === 'rejected'
                            ? __('identity::vendor-onboarding.banner.rejected_heading')
                            : __('identity::vendor-onboarding.banner.changes_requested_heading') }}
                    </p>
                    @if ($checklist->rejectionReason)
                        <p class="text-sm mt-1
                            {{ $checklist->rejectionState->value === 'rejected'
                                ? 'text-danger-700 dark:text-danger-300'
                                : 'text-warning-700 dark:text-warning-300' }}">
                            {{ $checklist->rejectionReason }}
                        </p>
                    @endif
                    <div class="mt-2">
                        @php
                            try {
                                $profileCTAUrl = \App\Modules\Identity\Filament\Vendor\Pages\VendorProfilePage::getUrl();
                            } catch (\Exception $e) {
                                $profileCTAUrl = '#';
                            }
                        @endphp
                        <x-filament::button
                            href="{{ $profileCTAUrl }}"
                            tag="a"
                            size="sm"
                            color="{{ $checklist->rejectionState->value === 'rejected' ? 'danger' : 'warning' }}"
                        >
                            {{ __('identity::vendor-onboarding.cta.resubmit_profile') }}
                        </x-filament::button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Progress header --}}
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                {{ __('identity::vendor-onboarding.progress', [
                    'done'  => $checklist->completedCount,
                    'total' => $checklist->totalCount,
                ]) }}
            </h2>
            <span class="text-sm font-medium text-gray-500">{{ $checklist->progressPercent }}%</span>
        </div>

        {{-- Progress bar --}}
        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 mb-4">
            <div
                class="bg-primary-500 h-2 rounded-full transition-all duration-500"
                style="width: {{ $checklist->progressPercent }}%"
            ></div>
        </div>

        {{-- 10-row checklist --}}
        <ul class="space-y-2">
            @foreach ($checklist->items as $item)
                <li class="flex items-start gap-3 py-2">
                    {{-- Status icon --}}
                    <div class="shrink-0 mt-0.5">
                        @switch($item->status->value)
                            @case('complete')
                                <x-heroicon-o-check-circle class="w-5 h-5 text-success-500" />
                                @break
                            @case('warning')
                                <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-warning-500" />
                                @break
                            @case('danger')
                                <x-heroicon-o-x-circle class="w-5 h-5 text-danger-500" />
                                @break
                            @case('info')
                                <x-heroicon-o-information-circle class="w-5 h-5 text-info-500" />
                                @break
                            @default
                                <x-heroicon-o-clock class="w-5 h-5 text-gray-400" />
                        @endswitch
                    </div>

                    {{-- Label + sub-text + link --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            @if ($item->url)
                                <a href="{{ $item->url }}" class="text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 hover:underline">
                                    {{ $item->label }}
                                </a>
                            @else
                                <span class="text-sm font-medium
                                    {{ $item->status->value === 'complete'
                                        ? 'text-gray-700 dark:text-gray-300'
                                        : 'text-gray-500 dark:text-gray-400' }}">
                                    {{ $item->label }}
                                </span>
                            @endif
                        </div>
                        @if ($item->subText)
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $item->subText }}</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>

        {{-- CTA: Next recommended action OR completion chip --}}
        <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-800">
            @if ($checklist->nextRecommendedAction)
                <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                    <span class="text-xs text-gray-500 dark:text-gray-400 font-medium uppercase tracking-wide">
                        {{ __('identity::vendor-onboarding.cta.next_action') }}
                    </span>
                    <x-filament::button
                        href="{{ $checklist->nextRecommendedAction->url }}"
                        tag="a"
                        size="sm"
                        color="primary"
                    >
                        {{ __('identity::vendor-onboarding.rows.' . $checklist->nextRecommendedAction->key->value . '.cta') }}
                    </x-filament::button>
                </div>
            @else
                <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-success-50 dark:bg-success-950 border border-success-200 dark:border-success-800">
                    <x-heroicon-o-check-badge class="w-4 h-4 text-success-600" />
                    <span class="text-sm font-medium text-success-700 dark:text-success-300">
                        {{ __('identity::vendor-onboarding.cta.onboarding_done') }}
                    </span>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
