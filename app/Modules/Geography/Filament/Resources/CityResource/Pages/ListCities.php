<?php

declare(strict_types=1);

namespace App\Modules\Geography\Filament\Resources\CityResource\Pages;

use App\Modules\Geography\Filament\Resources\CityResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCities extends ListRecords
{
    protected static string $resource = CityResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
