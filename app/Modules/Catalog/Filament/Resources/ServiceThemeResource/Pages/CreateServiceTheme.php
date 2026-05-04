<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\ServiceThemeResource\Pages;

use App\Modules\Catalog\Filament\Resources\ServiceThemeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateServiceTheme extends CreateRecord
{
    protected static string $resource = ServiceThemeResource::class;
}
