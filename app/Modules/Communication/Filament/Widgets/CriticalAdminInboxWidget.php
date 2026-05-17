<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Widgets;

use App\Modules\Communication\Domain\Enums\AdminInboxSeverity;
use App\Modules\Communication\Domain\Enums\AdminInboxStatus;
use App\Modules\Communication\Domain\Models\AdminInboxItem;
use App\Modules\Communication\Filament\Resources\AdminInboxResource;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CriticalAdminInboxWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 90;

    public static function canView(): bool
    {
        return auth()->user()?->can('view_any_admin_inbox_item') ?? false;
    }

    protected function getStats(): array
    {
        $count = AdminInboxItem::query()
            ->where('severity', AdminInboxSeverity::Critical->value)
            ->whereIn('status', [AdminInboxStatus::Unread->value, AdminInboxStatus::Read->value])
            ->count();

        return [
            Stat::make(
                label: __('communication::widgets.critical_admin_inbox_heading'),
                value: $count,
            )
                ->description(__('communication::widgets.critical_admin_inbox_description', ['count' => $count]))
                ->color($count > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-exclamation-triangle')
                ->url(AdminInboxResource::getUrl('index').'?tableFilters[severity][value]=critical'),
        ];
    }
}
