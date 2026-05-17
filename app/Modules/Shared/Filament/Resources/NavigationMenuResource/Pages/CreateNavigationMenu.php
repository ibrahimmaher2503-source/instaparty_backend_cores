<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Resources\NavigationMenuResource\Pages;

use App\Modules\Shared\Filament\Resources\NavigationMenuResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateNavigationMenu extends CreateRecord
{
    protected static string $resource = NavigationMenuResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return NavigationMenuResource::persistViaAction($data);
    }
}
