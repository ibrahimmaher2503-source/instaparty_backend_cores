<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\DigitalServiceResource\Pages;

use App\Modules\Catalog\Filament\Resources\DigitalServiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;

class ListDigitalServices extends ListRecords
{
    use Translatable;

    protected static string $resource = DigitalServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
