<?php

declare(strict_types=1);

namespace App\Modules\Advertising\Filament\Resources\VendorAdSubscriptionResource\Pages;

use App\Modules\Advertising\Filament\Resources\VendorAdSubscriptionResource;
use Filament\Resources\Pages\ListRecords;

class ListVendorAdSubscriptions extends ListRecords
{
    protected static string $resource = VendorAdSubscriptionResource::class;
}
