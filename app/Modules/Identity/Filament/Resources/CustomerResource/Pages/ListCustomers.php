<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\CustomerResource\Pages;

use App\Modules\Identity\Filament\Resources\CustomerResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;
}
