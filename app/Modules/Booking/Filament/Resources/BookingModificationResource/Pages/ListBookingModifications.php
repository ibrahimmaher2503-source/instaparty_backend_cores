<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Resources\BookingModificationResource\Pages;

use App\Modules\Booking\Filament\Resources\BookingModificationResource;
use Filament\Resources\Pages\ListRecords;

class ListBookingModifications extends ListRecords
{
    protected static string $resource = BookingModificationResource::class;
}
