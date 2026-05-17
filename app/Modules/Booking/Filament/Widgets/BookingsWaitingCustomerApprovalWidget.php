<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Widgets;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CustomerReviewState;
use App\Modules\Booking\Filament\Resources\BookingResource;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BookingsWaitingCustomerApprovalWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 40;

    public static function canView(): bool
    {
        return auth()->user()?->can('view_any_booking') ?? false;
    }

    protected function getStats(): array
    {
        $count = Booking::query()
            ->whereState('lifecycle_status', CustomerReviewState::class)
            ->count();

        return [
            Stat::make(
                label: __('booking::widgets.bookings_waiting_customer_approval_heading'),
                value: $count,
            )
                ->description(__('booking::widgets.bookings_waiting_customer_approval_description', ['count' => $count]))
                ->color($count > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-clock')
                ->url(BookingResource::getUrl('index').'?tableFilters[lifecycle_status][value]=customer_review'),
        ];
    }
}
