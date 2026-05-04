<?php

declare(strict_types=1);

namespace App\Modules\Geography\Filament\Resources\CountryResource\Pages;

use App\Modules\Geography\Filament\Resources\CountryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCountry extends CreateRecord
{
    protected static string $resource = CountryResource::class;
}
