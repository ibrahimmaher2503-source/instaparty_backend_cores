<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\VendorProfileResource\Pages;

use App\Modules\Identity\Filament\Resources\VendorProfileResource;
use Filament\Resources\Pages\ListRecords;

class ListVendorProfiles extends ListRecords
{
    protected static string $resource = VendorProfileResource::class;
}
