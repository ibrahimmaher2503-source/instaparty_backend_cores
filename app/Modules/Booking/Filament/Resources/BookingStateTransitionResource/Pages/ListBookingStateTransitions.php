<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Resources\BookingStateTransitionResource\Pages;

use App\Modules\Booking\Filament\Resources\BookingStateTransitionResource;
use Filament\Resources\Pages\ListRecords;

class ListBookingStateTransitions extends ListRecords
{
    protected static string $resource = BookingStateTransitionResource::class;
}
