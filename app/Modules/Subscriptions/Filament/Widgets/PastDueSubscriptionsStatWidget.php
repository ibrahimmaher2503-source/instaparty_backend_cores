<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Filament\Widgets;

use App\Modules\Subscriptions\Domain\Enums\InvoiceStatus;
use App\Modules\Subscriptions\Domain\Models\SubscriptionInvoice;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PastDueSubscriptionsStatWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $pastDueCount = SubscriptionInvoice::query()
            ->where('status', InvoiceStatus::PastDue->value)
            ->count();

        return [
            Stat::make(__('subscription.past_due_stat'), $pastDueCount)
                ->description(__('subscription.past_due_invoices_require_attention'))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('warning'),
        ];
    }
}
