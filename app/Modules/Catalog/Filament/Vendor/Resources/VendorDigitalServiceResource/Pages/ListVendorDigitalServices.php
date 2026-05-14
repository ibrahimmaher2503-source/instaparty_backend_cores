<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Vendor\Resources\VendorDigitalServiceResource\Pages;

use App\Modules\Catalog\Filament\Vendor\Resources\VendorDigitalServiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVendorDigitalServices extends ListRecords
{
    protected static string $resource = VendorDigitalServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
