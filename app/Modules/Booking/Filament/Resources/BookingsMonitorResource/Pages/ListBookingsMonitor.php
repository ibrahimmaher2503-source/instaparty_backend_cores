<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Resources\BookingsMonitorResource\Pages;

use App\Modules\Booking\Filament\Resources\BookingsMonitorResource;
use Filament\Resources\Pages\ListRecords;

class ListBookingsMonitor extends ListRecords
{
    protected static string $resource = BookingsMonitorResource::class;
}
