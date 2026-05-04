<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\UserDeviceResource\Pages;

use App\Modules\Identity\Filament\Resources\UserDeviceResource;
use Filament\Resources\Pages\ListRecords;

class ListUserDevices extends ListRecords
{
    protected static string $resource = UserDeviceResource::class;
}
