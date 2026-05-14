<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Filament\Resources\SubscriptionAuditEntryResource\Pages;

use App\Modules\Subscriptions\Filament\Resources\SubscriptionAuditEntryResource;
use Filament\Resources\Pages\ViewRecord;

class ViewSubscriptionAuditEntry extends ViewRecord
{
    protected static string $resource = SubscriptionAuditEntryResource::class;
}
