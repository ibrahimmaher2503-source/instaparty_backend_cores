<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\VendorBusinessHourResource\Pages;

use App\Modules\Identity\Filament\Resources\VendorBusinessHourResource;
use Filament\Resources\Pages\ListRecords;

class ListVendorBusinessHours extends ListRecords
{
    protected static string $resource = VendorBusinessHourResource::class;
}
