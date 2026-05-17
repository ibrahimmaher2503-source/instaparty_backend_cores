<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Widgets;

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Filament\Resources\BookingsMonitorResource;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LateVendorResponsesWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 30;

    public static function canView(): bool
    {
        return auth()->user()?->can('view_any_bookings_monitor') ?? false;
    }

    protected function getStats(): array
    {
        $count = BookingVendor::query()
            ->whereNotNull('response_deadline')
            ->where('response_deadline', '<', now())
            ->where('sub_status', VendorSubStatus::Pending->value)
            ->count();

        return [
            Stat::make(
                label: __('booking::widgets.late_vendor_responses_heading'),
                value: $count,
            )
                ->description(__('booking::widgets.late_vendor_responses_description', ['count' => $count]))
                ->color($count > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-clock')
                ->url(BookingsMonitorResource::getUrl('index')),
        ];
    }
}
