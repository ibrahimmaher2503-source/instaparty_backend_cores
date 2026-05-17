<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Resources\AppSettingResource\Pages;

use App\Modules\Shared\Filament\Resources\AppSettingResource;
use Filament\Resources\Pages\EditRecord;

class EditAppSetting extends EditRecord
{
    protected static string $resource = AppSettingResource::class;
}
