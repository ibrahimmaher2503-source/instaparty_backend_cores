<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\OccasionResource\Pages;

use App\Modules\Catalog\Filament\Resources\OccasionResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateOccasion extends CreateRecord
{
    use Translatable;

    protected static string $resource = OccasionResource::class;
}
