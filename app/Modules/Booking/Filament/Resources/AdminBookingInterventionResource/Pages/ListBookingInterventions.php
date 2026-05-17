<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Resources\AdminBookingInterventionResource\Pages;

use App\Modules\Booking\Filament\Resources\AdminBookingInterventionResource;
use Filament\Resources\Pages\ListRecords;

class ListBookingInterventions extends ListRecords
{
    protected static string $resource = AdminBookingInterventionResource::class;
}
