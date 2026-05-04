<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\ServiceThemeResource\Pages;

use App\Modules\Catalog\Filament\Resources\ServiceThemeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListServiceThemes extends ListRecords
{
    protected static string $resource = ServiceThemeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
