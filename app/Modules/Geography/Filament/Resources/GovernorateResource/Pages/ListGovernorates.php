<?php

declare(strict_types=1);

namespace App\Modules\Geography\Filament\Resources\GovernorateResource\Pages;

use App\Modules\Geography\Filament\Resources\GovernorateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;

class ListGovernorates extends ListRecords
{
    use Translatable;

    protected static string $resource = GovernorateResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
