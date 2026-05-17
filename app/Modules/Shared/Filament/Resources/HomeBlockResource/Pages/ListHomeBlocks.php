<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Resources\HomeBlockResource\Pages;

use App\Modules\Shared\Filament\Resources\HomeBlockResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHomeBlocks extends ListRecords
{
    protected static string $resource = HomeBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
