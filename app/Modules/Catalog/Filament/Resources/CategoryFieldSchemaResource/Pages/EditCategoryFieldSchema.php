<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\CategoryFieldSchemaResource\Pages;

use App\Modules\Catalog\Application\Actions\DeleteCategoryFieldSchemaAction;
use App\Modules\Catalog\Application\Actions\UpdateCategoryFieldSchemaAction;
use App\Modules\Catalog\Application\DTOs\CategoryFieldSchemaDTO;
use App\Modules\Catalog\Domain\Models\CategoryFieldSchema;
use App\Modules\Catalog\Filament\Resources\CategoryFieldSchemaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCategoryFieldSchema extends EditRecord
{
    protected static string $resource = CategoryFieldSchemaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->using(function (CategoryFieldSchema $record): bool {
                    app(DeleteCategoryFieldSchemaAction::class)->execute($record, auth()->user());

                    return true;
                }),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(UpdateCategoryFieldSchemaAction::class)->execute(
            $record,
            CategoryFieldSchemaDTO::fromArray($data),
            auth()->user(),
        );
    }
}
