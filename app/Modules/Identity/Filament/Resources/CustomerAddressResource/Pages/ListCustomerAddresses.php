<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\CustomerAddressResource\Pages;

use App\Modules\Identity\Filament\Resources\CustomerAddressResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomerAddresses extends ListRecords
{
    protected static string $resource = CustomerAddressResource::class;
}
