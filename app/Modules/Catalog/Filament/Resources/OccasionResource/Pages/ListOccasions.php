<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\OccasionResource\Pages;

use App\Modules\Catalog\Filament\Resources\OccasionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;

class ListOccasions extends ListRecords
{
    use Translatable;

    protected static string $resource = OccasionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
