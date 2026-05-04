<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\CategoryFieldSchemaResource\Pages;

use App\Modules\Catalog\Filament\Resources\CategoryFieldSchemaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCategoryFieldSchema extends CreateRecord
{
    protected static string $resource = CategoryFieldSchemaResource::class;
}
