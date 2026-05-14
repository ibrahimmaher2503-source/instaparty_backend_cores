<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\SaleServiceResource\Pages;

use App\Modules\Catalog\Filament\Resources\SaleServiceResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PendingSaleServicesPage extends ListRecords
{
    use Translatable;

    protected static string $resource = SaleServiceResource::class;

    public function getTitle(): string
    {
        return __('catalog.pending_sale_services');
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->emptyStateHeading(__('catalog.pending_queue_empty_sale'));
    }

    protected function getTableQuery(): ?Builder
    {
        return parent::getTableQuery()
            ?->pendingReview()
            ->whereNull('deleted_at');
    }

    protected function getTableFilters(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
