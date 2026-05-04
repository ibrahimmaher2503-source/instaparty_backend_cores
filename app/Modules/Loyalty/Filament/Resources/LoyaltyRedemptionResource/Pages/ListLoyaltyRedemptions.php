<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Filament\Resources\LoyaltyRedemptionResource\Pages;

use App\Modules\Loyalty\Filament\Resources\LoyaltyRedemptionResource;
use Filament\Resources\Pages\ListRecords;

class ListLoyaltyRedemptions extends ListRecords
{
    protected static string $resource = LoyaltyRedemptionResource::class;
}
