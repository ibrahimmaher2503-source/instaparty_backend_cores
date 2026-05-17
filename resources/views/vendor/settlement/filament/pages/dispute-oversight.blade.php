<x-filament-panels::page>
    @php $stats = $this->getStats(); @endphp

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <x-filament::card>
            <div class="text-sm text-gray-500">{{ __('settlement.pending_refunds') }}</div>
            <div class="text-2xl font-bold mt-1">{{ $stats['pending_refunds'] }}</div>
        </x-filament::card>
        <x-filament::card>
            <div class="text-sm text-gray-500">{{ __('settlement.pending_withdrawals') }}</div>
            <div class="text-2xl font-bold mt-1">{{ $stats['pending_withdrawals'] }}</div>
        </x-filament::card>
        <x-filament::card>
            <div class="text-sm text-gray-500">{{ __('settlement.total_disputed_amount') }}</div>
            <div class="text-2xl font-bold mt-1">{{ number_format($stats['total_disputed'] / 100, 2) }} EGP</div>
        </x-filament::card>
    </div>

    <div class="space-y-6">
        <x-filament::section :heading="__('settlement.pending_refunds_section')">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2 px-3">{{ __('settlement.ref') }}</th>
                            <th class="text-left py-2 px-3">{{ __('settlement.status') }}</th>
                            <th class="text-right py-2 px-3">{{ __('settlement.amount') }}</th>
                            <th class="text-left py-2 px-3">{{ __('settlement.reason') }}</th>
                            <th class="text-left py-2 px-3">{{ __('settlement.created_at') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->getPendingRefunds() as $row)
                        <tr class="border-b">
                            <td class="py-2 px-3 font-mono text-xs">{{ $row->gateway_ref }}</td>
                            <td class="py-2 px-3">
                                <span class="inline-block px-2 py-0.5 rounded text-xs font-medium {{ $row->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800' }}">
                                    {{ $row->status }}
                                </span>
                            </td>
                            <td class="py-2 px-3 text-right">{{ number_format($row->amount_minor / 100, 2) }} EGP</td>
                            <td class="py-2 px-3 text-gray-500">{{ $row->reason_notes ?? '—' }}</td>
                            <td class="py-2 px-3 text-gray-500">{{ \Carbon\Carbon::parse($row->created_at)->format('d M Y H:i') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section :heading="__('settlement.pending_withdrawals_section')">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2 px-3">{{ __('settlement.withdrawal_id') }}</th>
                            <th class="text-left py-2 px-3">{{ __('settlement.owner') }}</th>
                            <th class="text-right py-2 px-3">{{ __('settlement.amount') }}</th>
                            <th class="text-left py-2 px-3">{{ __('settlement.created_at') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->getPendingWithdrawals() as $row)
                        <tr class="border-b">
                            <td class="py-2 px-3 font-mono text-xs">{{ $row->public_id }}</td>
                            <td class="py-2 px-3 text-gray-500">{{ $row->owner_type }} #{{ $row->owner_id }}</td>
                            <td class="py-2 px-3 text-right">{{ number_format($row->requested_amount_minor / 100, 2) }} {{ $row->requested_amount_currency }}</td>
                            <td class="py-2 px-3 text-gray-500">{{ \Carbon\Carbon::parse($row->created_at)->format('d M Y H:i') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
