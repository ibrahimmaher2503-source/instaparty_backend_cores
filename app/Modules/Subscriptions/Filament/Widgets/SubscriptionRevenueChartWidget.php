<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class SubscriptionRevenueChartWidget extends ChartWidget
{
    protected static ?string $heading = null;

    public function getHeading(): ?string
    {
        return __('subscriptions.revenue_by_month');
    }

    protected function getData(): array
    {
        $rows = DB::table('subscription_payments')
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, SUM(amount_minor) as revenue")
            ->where('status', 'succeeded')
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m')")
            ->orderBy('month')
            ->get();

        return [
            'datasets' => [
                [
                    'label'           => 'Revenue (EGP)',
                    'data'            => $rows->pluck('revenue')->map(fn ($v) => round($v / 100, 2))->all(),
                    'backgroundColor' => '#6366f1',
                    'borderColor'     => '#6366f1',
                ],
            ],
            'labels' => $rows->pluck('month')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
