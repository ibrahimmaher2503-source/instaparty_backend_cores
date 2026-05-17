<?php

declare(strict_types=1);

namespace App\Modules\Advertising\Filament\Pages;

use App\Modules\Advertising\Filament\Widgets\AdRevenueChartWidget;
use App\Modules\Advertising\Filament\Widgets\AdvertisingStatsWidget;
use Filament\Pages\Page;

class AdvertisingAnalyticsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';

    protected static string $view = 'filament.pages.advertising-analytics';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.advertising');
    }

    public static function getNavigationLabel(): string
    {
        return __('advertising.analytics');
    }

    public function getHeaderWidgets(): array
    {
        return [
            AdvertisingStatsWidget::class,
            AdRevenueChartWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | string | array
    {
        return 2;
    }
}
