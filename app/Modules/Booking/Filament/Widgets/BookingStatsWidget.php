<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Widgets;

use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Models\Booking;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BookingStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Draft Bookings', Booking::where('lifecycle_status', LifecycleStatus::Draft)->count())
                ->color('gray'),
            Stat::make('Submitted Today', Booking::where('lifecycle_status', LifecycleStatus::Submitted)
                ->whereDate('submitted_at', today())->count())
                ->color('warning'),
            Stat::make('Confirmed Today', Booking::where('lifecycle_status', LifecycleStatus::Confirmed)
                ->whereDate('confirmed_at', today())->count())
                ->color('success'),
        ];
    }
}
