<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Resources\WithdrawalsQueueResource\Pages;

use App\Modules\Settlement\Filament\Resources\WithdrawalsQueueResource;
use Filament\Resources\Pages\ListRecords;

class ListWithdrawalsQueue extends ListRecords
{
    protected static string $resource = WithdrawalsQueueResource::class;
}
