<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Vendor\Pages;

use App\Modules\Identity\Filament\Vendor\Widgets\VendorOnboardingChecklistWidget;
use App\Modules\Shared\Filament\Vendor\Widgets\VendorStatsOverviewWidget;
use Filament\Pages\Dashboard;
use Illuminate\Contracts\Support\Htmlable;

class VendorDashboardPage extends Dashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?int $navigationSort = -2;

    public function getTitle(): string|Htmlable
    {
        return __('vendor-portal.dashboard.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('vendor-portal.dashboard.title');
    }

    public function getHeaderWidgets(): array
    {
        return [
            VendorOnboardingChecklistWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 1;
    }

    public function getWidgets(): array
    {
        return [
            VendorStatsOverviewWidget::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        return 2;
    }
}
