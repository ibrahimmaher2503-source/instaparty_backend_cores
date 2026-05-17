<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Resources\AppSettingResource\Pages;

use App\Modules\Shared\Filament\Resources\AppSettingResource;
use Filament\Resources\Pages\ListRecords;

class ListAppSettings extends ListRecords
{
    protected static string $resource = AppSettingResource::class;
}
