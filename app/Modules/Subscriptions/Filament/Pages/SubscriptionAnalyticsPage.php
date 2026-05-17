<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Filament\Pages;

use App\Modules\Subscriptions\Filament\Widgets\ExpiringSubscriptionsWidget;
use App\Modules\Subscriptions\Filament\Widgets\SubscriptionRevenueChartWidget;
use App\Modules\Subscriptions\Filament\Widgets\SubscriptionStatsWidget;
use Filament\Pages\Page;

class SubscriptionAnalyticsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string $view = 'filament.pages.subscription-analytics';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.subscriptions');
    }

    public static function getNavigationLabel(): string
    {
        return __('subscriptions.analytics');
    }

    public function getHeaderWidgets(): array
    {
        return [
            SubscriptionStatsWidget::class,
            SubscriptionRevenueChartWidget::class,
            ExpiringSubscriptionsWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | string | array
    {
        return 2;
    }
}
