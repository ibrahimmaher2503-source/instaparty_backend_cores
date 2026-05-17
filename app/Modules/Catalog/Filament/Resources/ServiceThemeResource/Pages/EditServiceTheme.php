<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\ServiceThemeResource\Pages;

use App\Modules\Catalog\Application\Actions\DeleteServiceThemeAction;
use App\Modules\Catalog\Application\Actions\UpdateServiceThemeAction;
use App\Modules\Catalog\Application\DTOs\ServiceThemeDTO;
use App\Modules\Catalog\Domain\Models\ServiceTheme;
use App\Modules\Catalog\Filament\Resources\ServiceThemeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditServiceTheme extends EditRecord
{
    protected static string $resource = ServiceThemeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->using(function (ServiceTheme $record): bool {
                    app(DeleteServiceThemeAction::class)->execute($record, auth()->user());

                    return true;
                }),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(UpdateServiceThemeAction::class)->execute(
            $record,
            ServiceThemeDTO::fromArray($data),
            auth()->user(),
        );
    }
}
