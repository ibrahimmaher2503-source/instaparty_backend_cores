<?php

declare(strict_types=1);

namespace App\Modules\Geography\Filament\Resources\CityResource\Pages;

use App\Modules\Geography\Filament\Resources\CityResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateCity extends CreateRecord
{
    use Translatable;

    protected static string $resource = CityResource::class;
}
