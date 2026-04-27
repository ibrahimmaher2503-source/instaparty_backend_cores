<?php

declare(strict_types=1);

namespace App\Modules\Geography\Filament\Resources\GovernorateResource\Pages;

use App\Modules\Geography\Filament\Resources\GovernorateResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateGovernorate extends CreateRecord
{
    use Translatable;

    protected static string $resource = GovernorateResource::class;
}
