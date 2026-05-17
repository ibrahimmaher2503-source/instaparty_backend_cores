<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Widgets;

use App\Modules\Settlement\Domain\Enums\WithdrawalStatus;
use App\Modules\Settlement\Domain\Models\Withdrawal;
use App\Modules\Settlement\Filament\Resources\WithdrawalsQueueResource;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PendingWithdrawalsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 70;

    public static function canView(): bool
    {
        return auth()->user()?->can('view_any_withdrawals_queue') ?? false;
    }

    protected function getStats(): array
    {
        $count = Withdrawal::query()
            ->where('status', WithdrawalStatus::Pending->value)
            ->count();

        return [
            Stat::make(
                label: __('settlement::widgets.pending_withdrawals_heading'),
                value: $count,
            )
                ->description(__('settlement::widgets.pending_withdrawals_description', ['count' => $count]))
                ->color($count > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-banknotes')
                ->url(WithdrawalsQueueResource::getUrl('index')),
        ];
    }
}
