<?php

declare(strict_types=1);

namespace App\Modules\Geography\Filament\Resources\RegionResource\Pages;

use App\Modules\Geography\Filament\Resources\RegionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;

class EditRegion extends EditRecord
{
    use Translatable;

    protected static string $resource = RegionResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
