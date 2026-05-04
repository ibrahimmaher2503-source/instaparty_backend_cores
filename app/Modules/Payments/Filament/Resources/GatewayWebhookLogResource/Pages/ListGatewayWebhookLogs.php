<?php

declare(strict_types=1);

namespace App\Modules\Payments\Filament\Resources\GatewayWebhookLogResource\Pages;

use App\Modules\Payments\Filament\Resources\GatewayWebhookLogResource;
use Filament\Resources\Pages\ListRecords;

class ListGatewayWebhookLogs extends ListRecords
{
    protected static string $resource = GatewayWebhookLogResource::class;
}
