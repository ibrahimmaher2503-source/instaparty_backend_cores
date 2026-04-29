<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\CategoryResource\Pages;

use App\Modules\Catalog\Filament\Resources\CategoryResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateCategory extends CreateRecord
{
    use Translatable;

    protected static string $resource = CategoryResource::class;
}
