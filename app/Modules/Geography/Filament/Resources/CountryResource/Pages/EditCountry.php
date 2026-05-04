<?php

declare(strict_types=1);

namespace App\Modules\Geography\Filament\Resources\CountryResource\Pages;

use App\Modules\Geography\Filament\Resources\CountryResource;
use Filament\Resources\Pages\EditRecord;

class EditCountry extends EditRecord
{
    protected static string $resource = CountryResource::class;
}
