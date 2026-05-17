<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Widgets;

use App\Modules\Catalog\Domain\Models\ExcelImport;
use App\Modules\Catalog\Filament\Resources\ExcelImportResource;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ExcelImportsWithErrorsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 80;

    public static function canView(): bool
    {
        return auth()->user()?->can('view_any_excel_import') ?? false;
    }

    protected function getStats(): array
    {
        $count = ExcelImport::query()
            ->where(function ($q): void {
                $q->where('status', 'failed')->orWhere('error_rows', '>', 0);
            })
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return [
            Stat::make(
                label: __('catalog::widgets.excel_imports_with_errors_heading'),
                value: $count,
            )
                ->description(__('catalog::widgets.excel_imports_with_errors_description', ['count' => $count]))
                ->color($count > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-document-chart-bar')
                ->url(ExcelImportResource::getUrl('index')),
        ];
    }
}
