<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Resources\HomeBlockResource\Pages;

use App\Modules\Shared\Filament\Resources\HomeBlockResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateHomeBlock extends CreateRecord
{
    protected static string $resource = HomeBlockResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return HomeBlockResource::persistViaAction($data, null);
    }
}
