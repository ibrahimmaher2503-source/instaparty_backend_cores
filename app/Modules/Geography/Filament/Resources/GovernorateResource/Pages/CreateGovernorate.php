<?php

declare(strict_types=1);

namespace App\Modules\Geography\Filament\Resources\GovernorateResource\Pages;

use App\Modules\Geography\Filament\Resources\GovernorateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGovernorate extends CreateRecord
{
    protected static string $resource = GovernorateResource::class;
}
