<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Widgets;

use App\Modules\Identity\Domain\Enums\ApprovalStatus;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Resources\VendorApprovalQueueResource;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PendingVendorApprovalsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 10;

    public static function canView(): bool
    {
        return auth()->user()?->can('view_any_vendor_profile') ?? false;
    }

    protected function getStats(): array
    {
        $count = VendorProfile::query()
            ->where('approval_status', ApprovalStatus::Pending->value)
            ->count();

        return [
            Stat::make(
                label: __('identity::widgets.pending_vendor_approvals_heading'),
                value: $count,
            )
                ->description(__('identity::widgets.pending_vendor_approvals_description', ['count' => $count]))
                ->color($count > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-user-plus')
                ->url(VendorApprovalQueueResource::getUrl('index')),
        ];
    }
}
