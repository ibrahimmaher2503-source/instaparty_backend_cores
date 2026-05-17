<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Resources\WithdrawalResource\Pages;

use App\Modules\Settlement\Filament\Resources\WithdrawalResource;
use Filament\Resources\Pages\ListRecords;

class ListWithdrawals extends ListRecords
{
    protected static string $resource = WithdrawalResource::class;
}
