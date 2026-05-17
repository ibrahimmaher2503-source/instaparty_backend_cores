<?php

declare(strict_types=1);

namespace App\Modules\Advertising\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class AdRevenueChartWidget extends ChartWidget
{
    protected static ?string $heading = null;

    public function getHeading(): ?string
    {
        return __('advertising.revenue_by_month');
    }

    protected function getData(): array
    {
        $rows = DB::table('vendor_ad_subscriptions')
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, SUM(total_minor) as revenue")
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m')")
            ->orderBy('month')
            ->get();

        return [
            'datasets' => [
                [
                    'label'           => 'Ad Revenue (EGP)',
                    'data'            => $rows->pluck('revenue')->map(fn ($v) => round($v / 100, 2))->all(),
                    'backgroundColor' => '#f59e0b',
                    'borderColor'     => '#f59e0b',
                ],
            ],
            'labels' => $rows->pluck('month')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
