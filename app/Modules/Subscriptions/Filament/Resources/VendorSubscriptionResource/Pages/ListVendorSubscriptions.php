<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Filament\Resources\VendorSubscriptionResource\Pages;

use App\Modules\Subscriptions\Filament\Resources\VendorSubscriptionResource;
use Filament\Resources\Pages\ListRecords;

class ListVendorSubscriptions extends ListRecords
{
    protected static string $resource = VendorSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
