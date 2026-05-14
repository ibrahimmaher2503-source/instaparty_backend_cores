<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Vendor\Resources\VendorSaleServiceResource\Pages;

use App\Modules\Catalog\Filament\Vendor\Resources\VendorSaleServiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVendorSaleServices extends ListRecords
{
    protected static string $resource = VendorSaleServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
