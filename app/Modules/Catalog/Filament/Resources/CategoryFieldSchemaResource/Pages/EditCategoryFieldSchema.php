<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\CategoryFieldSchemaResource\Pages;

use App\Modules\Catalog\Filament\Resources\CategoryFieldSchemaResource;
use Filament\Resources\Pages\EditRecord;

class EditCategoryFieldSchema extends EditRecord
{
    protected static string $resource = CategoryFieldSchemaResource::class;
}
