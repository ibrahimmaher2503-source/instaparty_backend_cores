<?php

declare(strict_types=1);

namespace App\Modules\Geography\Filament\Resources\CityResource\Pages;

use App\Modules\Geography\Filament\Resources\CityResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;

class EditCity extends EditRecord
{
    use Translatable;

    protected static string $resource = CityResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
