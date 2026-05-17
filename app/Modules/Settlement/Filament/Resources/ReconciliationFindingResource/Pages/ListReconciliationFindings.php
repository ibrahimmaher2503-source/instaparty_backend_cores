<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Resources\ReconciliationFindingResource\Pages;

use App\Modules\Settlement\Filament\Resources\ReconciliationFindingResource;
use Filament\Resources\Pages\ListRecords;

class ListReconciliationFindings extends ListRecords
{
    protected static string $resource = ReconciliationFindingResource::class;
}
