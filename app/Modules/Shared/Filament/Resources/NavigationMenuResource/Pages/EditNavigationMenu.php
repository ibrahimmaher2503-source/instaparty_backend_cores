<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Resources\NavigationMenuResource\Pages;

use App\Modules\Shared\Filament\Resources\NavigationMenuResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditNavigationMenu extends EditRecord
{
    protected static string $resource = NavigationMenuResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return NavigationMenuResource::persistViaAction($data);
    }
}
