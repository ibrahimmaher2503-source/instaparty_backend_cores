<?php

declare(strict_types=1);

namespace App\Modules\Geography\Filament\Resources\RegionResource\Pages;

use App\Modules\Geography\Filament\Resources\RegionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;

class ListRegions extends ListRecords
{
    use Translatable;

    protected static string $resource = RegionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
