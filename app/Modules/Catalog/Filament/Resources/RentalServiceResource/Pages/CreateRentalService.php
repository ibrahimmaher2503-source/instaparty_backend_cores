<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\RentalServiceResource\Pages;

use App\Modules\Catalog\Filament\Resources\RentalServiceResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateRentalService extends CreateRecord
{
    use Translatable;

    protected static string $resource = RentalServiceResource::class;
}
