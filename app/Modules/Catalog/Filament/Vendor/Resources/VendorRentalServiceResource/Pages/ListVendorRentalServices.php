<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Vendor\Resources\VendorRentalServiceResource\Pages;

use App\Modules\Catalog\Filament\Vendor\Resources\VendorRentalServiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVendorRentalServices extends ListRecords
{
    protected static string $resource = VendorRentalServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
