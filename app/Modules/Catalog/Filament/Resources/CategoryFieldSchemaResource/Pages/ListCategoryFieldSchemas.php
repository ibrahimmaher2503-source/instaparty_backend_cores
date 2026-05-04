<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\CategoryFieldSchemaResource\Pages;

use App\Modules\Catalog\Filament\Resources\CategoryFieldSchemaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCategoryFieldSchemas extends ListRecords
{
    protected static string $resource = CategoryFieldSchemaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
