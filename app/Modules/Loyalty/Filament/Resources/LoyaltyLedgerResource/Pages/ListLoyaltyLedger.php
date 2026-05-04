<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Filament\Resources\LoyaltyLedgerResource\Pages;

use App\Modules\Loyalty\Filament\Resources\LoyaltyLedgerResource;
use Filament\Resources\Pages\ListRecords;

class ListLoyaltyLedger extends ListRecords
{
    protected static string $resource = LoyaltyLedgerResource::class;
}
