<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Resources\NavigationMenuResource\Pages;

use App\Modules\Shared\Filament\Resources\NavigationMenuResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNavigationMenus extends ListRecords
{
    protected static string $resource = NavigationMenuResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
