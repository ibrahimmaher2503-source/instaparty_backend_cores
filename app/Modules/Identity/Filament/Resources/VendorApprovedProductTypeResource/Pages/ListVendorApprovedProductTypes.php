<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\VendorApprovedProductTypeResource\Pages;

use App\Modules\Identity\Filament\Resources\VendorApprovedProductTypeResource;
use Filament\Resources\Pages\ListRecords;

class ListVendorApprovedProductTypes extends ListRecords
{
    protected static string $resource = VendorApprovedProductTypeResource::class;
}
