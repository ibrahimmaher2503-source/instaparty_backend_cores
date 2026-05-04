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
            Stat::make(__('booking::booking.dashboard.draft_bookings'), Booking::where('lifecycle_status', LifecycleStatus::Draft)->count())
                ->color('gray'),
            Stat::make(__('booking::booking.dashboard.submitted_today'), Booking::where('lifecycle_status', LifecycleStatus::Submitted)
                ->whereDate('submitted_at', today())->count())
                ->color('warning'),
            Stat::make(__('booking::booking.dashboard.confirmed_today'), Booking::where('lifecycle_status', LifecycleStatus::Confirmed)
                ->whereDate('confirmed_at', today())->count())
                ->color('success'),
        ];
    }
}
