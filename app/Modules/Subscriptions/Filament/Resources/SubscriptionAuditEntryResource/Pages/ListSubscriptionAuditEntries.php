<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Filament\Resources\SubscriptionAuditEntryResource\Pages;

use App\Modules\Subscriptions\Filament\Resources\SubscriptionAuditEntryResource;
use Filament\Resources\Pages\ListRecords;

class ListSubscriptionAuditEntries extends ListRecords
{
    protected static string $resource = SubscriptionAuditEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
