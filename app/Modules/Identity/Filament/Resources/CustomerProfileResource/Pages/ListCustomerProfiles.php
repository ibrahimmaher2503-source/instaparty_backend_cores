<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\CustomerProfileResource\Pages;

use App\Modules\Identity\Filament\Resources\CustomerProfileResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomerProfiles extends ListRecords
{
    protected static string $resource = CustomerProfileResource::class;
}
