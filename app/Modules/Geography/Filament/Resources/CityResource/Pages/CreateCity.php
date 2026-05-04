<?php

declare(strict_types=1);

namespace App\Modules\Geography\Filament\Resources\CityResource\Pages;

use App\Modules\Geography\Filament\Resources\CityResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCity extends CreateRecord
{
    protected static string $resource = CityResource::class;
}
