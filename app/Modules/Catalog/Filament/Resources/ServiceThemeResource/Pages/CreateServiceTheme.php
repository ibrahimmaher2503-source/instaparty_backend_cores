<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\ServiceThemeResource\Pages;

use App\Modules\Catalog\Application\Actions\CreateServiceThemeAction;
use App\Modules\Catalog\Application\DTOs\ServiceThemeDTO;
use App\Modules\Catalog\Filament\Resources\ServiceThemeResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateServiceTheme extends CreateRecord
{
    protected static string $resource = ServiceThemeResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateServiceThemeAction::class)->execute(
            ServiceThemeDTO::fromArray($data),
            auth()->user(),
        );
    }
}
