<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\CategoryResource\Pages;

use App\Modules\Catalog\Filament\Resources\CategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;

class EditCategory extends EditRecord
{
    use Translatable;

    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
