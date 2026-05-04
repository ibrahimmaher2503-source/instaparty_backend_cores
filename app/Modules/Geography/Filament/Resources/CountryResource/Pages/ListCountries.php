<?php

declare(strict_types=1);

namespace App\Modules\Geography\Filament\Resources\CountryResource\Pages;

use App\Modules\Geography\Filament\Resources\CountryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCountries extends ListRecords
{
    protected static string $resource = CountryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
