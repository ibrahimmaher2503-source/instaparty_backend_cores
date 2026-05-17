<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\CategoryFieldSchemaResource\Pages;

use App\Modules\Catalog\Application\Actions\CreateCategoryFieldSchemaAction;
use App\Modules\Catalog\Application\DTOs\CategoryFieldSchemaDTO;
use App\Modules\Catalog\Filament\Resources\CategoryFieldSchemaResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCategoryFieldSchema extends CreateRecord
{
    protected static string $resource = CategoryFieldSchemaResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateCategoryFieldSchemaAction::class)->execute(
            CategoryFieldSchemaDTO::fromArray($data),
            auth()->user(),
        );
    }
}
