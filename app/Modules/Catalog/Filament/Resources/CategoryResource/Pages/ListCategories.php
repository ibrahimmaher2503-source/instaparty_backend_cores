<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\CategoryResource\Pages;

use App\Modules\Catalog\Filament\Resources\CategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;

class ListCategories extends ListRecords
{
    use Translatable;

    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
