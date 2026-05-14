<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\RentalServiceResource\Pages;

use App\Modules\Catalog\Filament\Resources\RentalServiceResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PendingRentalServicesPage extends ListRecords
{
    use Translatable;

    protected static string $resource = RentalServiceResource::class;

    public function getTitle(): string
    {
        return __('catalog.pending_rental_services');
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->emptyStateHeading(__('catalog.pending_queue_empty_rental'));
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
