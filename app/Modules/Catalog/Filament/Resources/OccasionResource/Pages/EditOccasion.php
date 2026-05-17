<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\OccasionResource\Pages;

use App\Modules\Catalog\Application\Actions\DeleteOccasionAction;
use App\Modules\Catalog\Application\Actions\UpdateOccasionAction;
use App\Modules\Catalog\Application\DTOs\OccasionDTO;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Filament\Resources\OccasionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;
use Illuminate\Database\Eloquent\Model;

class EditOccasion extends EditRecord
{
    use Translatable;

    protected static string $resource = OccasionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->using(function (Occasion $record): bool {
                    app(DeleteOccasionAction::class)->execute($record, auth()->user());

                    return true;
                }),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(UpdateOccasionAction::class)->execute(
            $record,
            OccasionDTO::fromArray($data),
            auth()->user(),
        );
    }
}
