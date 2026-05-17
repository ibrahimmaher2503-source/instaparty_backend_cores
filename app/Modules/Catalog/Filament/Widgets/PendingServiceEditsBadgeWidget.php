<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Widgets;

use App\Modules\Catalog\Domain\Enums\ServiceChangeRequestStatus;
use App\Modules\Catalog\Domain\Models\ServiceChangeRequest;
use App\Modules\Catalog\Filament\Pages\PendingServiceEditsPage;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PendingServiceEditsBadgeWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 25;

    public static function canView(): bool
    {
        return auth()->user()?->can('view_any_rental_service') ?? false;
    }

    protected function getStats(): array
    {
        $pendingCount = ServiceChangeRequest::query()
            ->where('status', ServiceChangeRequestStatus::Pending->value)
            ->count();

        $awaitingCount = ServiceChangeRequest::query()
            ->where('status', ServiceChangeRequestStatus::AwaitingClarification->value)
            ->count();

        return [
            Stat::make(__('catalog::widgets.pending_service_edits_heading'), $pendingCount)
                ->color($pendingCount > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-pencil-square')
                ->url(PendingServiceEditsPage::getUrl()),

            Stat::make(__('catalog::widgets.awaiting_clarification_heading'), $awaitingCount)
                ->color($awaitingCount > 0 ? 'info' : 'success')
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->url(PendingServiceEditsPage::getUrl()),
        ];
    }
}
