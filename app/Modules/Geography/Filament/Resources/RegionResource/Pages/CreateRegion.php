<?php

declare(strict_types=1);

namespace App\Modules\Geography\Filament\Resources\RegionResource\Pages;

use App\Modules\Geography\Filament\Resources\RegionResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateRegion extends CreateRecord
{
    use Translatable;

    protected static string $resource = RegionResource::class;
}
