<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Filament\Resources\SubscriptionInvoiceResource\Pages;

use App\Modules\Subscriptions\Filament\Resources\SubscriptionInvoiceResource;
use Filament\Resources\Pages\ViewRecord;

class ViewSubscriptionInvoice extends ViewRecord
{
    protected static string $resource = SubscriptionInvoiceResource::class;
}
