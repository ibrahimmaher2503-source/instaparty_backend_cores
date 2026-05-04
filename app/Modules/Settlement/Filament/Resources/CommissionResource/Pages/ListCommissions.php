<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Resources\CommissionResource\Pages;

use App\Modules\Settlement\Filament\Resources\CommissionResource;
use Filament\Resources\Pages\ListRecords;

class ListCommissions extends ListRecords
{
    protected static string $resource = CommissionResource::class;
}
