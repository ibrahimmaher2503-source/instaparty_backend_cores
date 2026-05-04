<?php

declare(strict_types=1);

namespace App\Modules\Geography\Filament\Resources\RegionResource\Pages;

use App\Modules\Geography\Filament\Resources\RegionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRegions extends ListRecords
{
    protected static string $resource = RegionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
