<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <x-filament::card>
            <div class="text-sm text-gray-500">{{ __('settlement.reconciliation_dashboard.open_high_findings') }}</div>
            <div class="text-2xl font-bold mt-1 {{ $this->getOpenHighFindingsCount() > 0 ? 'text-danger-600' : 'text-success-600' }}">
                {{ $this->getOpenHighFindingsCount() }}
            </div>
        </x-filament::card>

        <x-filament::card>
            <div class="text-sm text-gray-500">{{ __('settlement.reconciliation_dashboard.auto_repaired_today') }}</div>
            <div class="text-2xl font-bold mt-1 text-warning-600">{{ $this->getTotalAutoRepairedToday() }}</div>
        </x-filament::card>

        <x-filament::card>
            <div class="text-sm text-gray-500">{{ __('settlement.reconciliation_dashboard.runs_last_7_days') }}</div>
            <div class="text-2xl font-bold mt-1">
                {{ collect($this->getLast7DaysRunTrend())->sum('runs') }}
            </div>
        </x-filament::card>
    </div>

    <x-filament::section :heading="__('settlement.reconciliation_dashboard.run_trend_heading')">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="text-left py-2 px-3">{{ __('settlement.reconciliation_dashboard.date') }}</th>
                        <th class="text-right py-2 px-3">{{ __('settlement.reconciliation_dashboard.runs') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($this->getLast7DaysRunTrend() as $row)
                    <tr class="border-b">
                        <td class="py-2 px-3">{{ $row['date'] }}</td>
                        <td class="py-2 px-3 text-right">{{ $row['runs'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
