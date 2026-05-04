<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Resources\SettlementRunResource\Pages;

use App\Modules\Settlement\Filament\Resources\SettlementRunResource;
use Filament\Resources\Pages\ListRecords;

class ListSettlementRuns extends ListRecords
{
    protected static string $resource = SettlementRunResource::class;
}
