<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Resources\LedgerTransactionGroupResource\Pages;

use App\Modules\Settlement\Filament\Resources\LedgerTransactionGroupResource;
use Filament\Resources\Pages\ListRecords;

class ListLedgerTransactionGroups extends ListRecords
{
    protected static string $resource = LedgerTransactionGroupResource::class;
}
