<?php

declare(strict_types=1);

namespace App\Modules\Payments\Filament\Widgets;

use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Filament\Resources\PaymentResource;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FailedPaymentsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 50;

    public static function canView(): bool
    {
        return auth()->user()?->can('view_any_payment') ?? false;
    }

    protected function getStats(): array
    {
        $count = Payment::query()
            ->where('status', PaymentStatus::Failed->value)
            ->where('created_at', '>=', now()->subHours(48))
            ->count();

        return [
            Stat::make(
                label: __('payments::widgets.failed_payments_heading'),
                value: $count,
            )
                ->description(__('payments::widgets.failed_payments_description', ['count' => $count]))
                ->color($count > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-credit-card')
                ->url(PaymentResource::getUrl('index').'?tableFilters[status][value]=failed'),
        ];
    }
}
