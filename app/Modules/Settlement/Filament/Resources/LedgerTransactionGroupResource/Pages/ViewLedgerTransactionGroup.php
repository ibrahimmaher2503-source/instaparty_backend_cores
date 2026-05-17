<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Resources\LedgerTransactionGroupResource\Pages;

use App\Modules\Settlement\Filament\Resources\LedgerTransactionGroupResource;
use Filament\Resources\Pages\ViewRecord;

class ViewLedgerTransactionGroup extends ViewRecord
{
    protected static string $resource = LedgerTransactionGroupResource::class;
}
