<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\SaleServiceResource\Pages;

use App\Modules\Catalog\Filament\Resources\SaleServiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;

class ListSaleServices extends ListRecords
{
    use Translatable;

    protected static string $resource = SaleServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
