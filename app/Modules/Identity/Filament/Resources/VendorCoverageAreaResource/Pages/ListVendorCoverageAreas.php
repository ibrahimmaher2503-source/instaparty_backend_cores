<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\VendorCoverageAreaResource\Pages;

use App\Modules\Identity\Filament\Resources\VendorCoverageAreaResource;
use Filament\Resources\Pages\ListRecords;

class ListVendorCoverageAreas extends ListRecords
{
    protected static string $resource = VendorCoverageAreaResource::class;
}
