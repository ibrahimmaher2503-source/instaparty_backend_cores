<?php

declare(strict_types=1);

namespace App\Modules\Geography\Filament\Resources\GovernorateResource\Pages;

use App\Modules\Geography\Filament\Resources\GovernorateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;

class EditGovernorate extends EditRecord
{
    use Translatable;

    protected static string $resource = GovernorateResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
