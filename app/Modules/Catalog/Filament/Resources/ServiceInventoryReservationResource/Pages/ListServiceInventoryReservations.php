<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\ServiceInventoryReservationResource\Pages;

use App\Modules\Catalog\Filament\Resources\ServiceInventoryReservationResource;
use Filament\Resources\Pages\ListRecords;

class ListServiceInventoryReservations extends ListRecords
{
    protected static string $resource = ServiceInventoryReservationResource::class;
}
