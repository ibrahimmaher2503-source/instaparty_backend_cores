<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Resources\WalletLedgerViewerResource\Pages;

use App\Modules\Settlement\Filament\Resources\WalletLedgerViewerResource;
use Filament\Resources\Pages\ListRecords;

class ListWalletLedger extends ListRecords
{
    protected static string $resource = WalletLedgerViewerResource::class;
}
