<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\CategoryResource\Pages;

use App\Modules\Catalog\Application\Actions\DeleteCategoryAction;
use App\Modules\Catalog\Application\Actions\UpdateCategoryAction;
use App\Modules\Catalog\Application\DTOs\CategoryDTO;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Filament\Resources\CategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;
use Illuminate\Database\Eloquent\Model;

class EditCategory extends EditRecord
{
    use Translatable;

    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->using(function (Category $record): bool {
                    app(DeleteCategoryAction::class)->execute($record, auth()->user());

                    return true;
                }),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(UpdateCategoryAction::class)->execute(
            $record,
            CategoryDTO::fromArray($data),
            auth()->user(),
        );
    }
}
