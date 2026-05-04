<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Resources\BookingResource\Pages;

use App\Modules\Booking\Filament\Resources\BookingResource;
use Filament\Resources\Pages\ListRecords;

class ListBookings extends ListRecords
{
    protected static string $resource = BookingResource::class;
}
