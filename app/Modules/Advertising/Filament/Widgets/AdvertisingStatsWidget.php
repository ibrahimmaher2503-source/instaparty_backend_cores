<?php

declare(strict_types=1);

namespace App\Modules\Advertising\Filament\Widgets;

use App\Modules\Advertising\Domain\Enums\AdSubscriptionStatus;
use App\Modules\Advertising\Domain\Models\VendorAdSubscription;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class AdvertisingStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $activeCount = VendorAdSubscription::where('status', AdSubscriptionStatus::Active)->count();

        $totalRevenue = VendorAdSubscription::whereIn('status', [
            AdSubscriptionStatus::Active->value,
            AdSubscriptionStatus::Expired->value,
        ])->sum('total_minor');

        $impressionsThisMonth = VendorAdSubscription::whereBetween('created_at', [
            now()->startOfMonth(), now()->endOfMonth(),
        ])->sum('impression_count');

        $topPlacement = DB::table('vendor_ad_subscriptions')
            ->join('advertisement_packages', 'advertisement_packages.id', '=', 'vendor_ad_subscriptions.advertisement_package_id')
            ->selectRaw('advertisement_packages.placement_type, SUM(vendor_ad_subscriptions.total_minor) as revenue')
            ->groupBy('advertisement_packages.placement_type')
            ->orderByDesc('revenue')
            ->value('placement_type') ?? '—';

        return [
            Stat::make(__('advertising.active_subscriptions'), $activeCount)
                ->icon('heroicon-o-tv')
                ->color('success'),

            Stat::make(__('advertising.total_revenue'), number_format($totalRevenue / 100, 2) . ' EGP')
                ->icon('heroicon-o-banknotes')
                ->color('primary'),

            Stat::make(__('advertising.impressions_this_month'), number_format($impressionsThisMonth))
                ->icon('heroicon-o-eye')
                ->color('info'),

            Stat::make(__('advertising.top_placement'), $topPlacement)
                ->icon('heroicon-o-star')
                ->color('warning'),
        ];
    }
}
