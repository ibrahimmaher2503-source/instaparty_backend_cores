<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Resources\ReconciliationRunResource\Pages;

use App\Modules\Settlement\Filament\Resources\ReconciliationRunResource;
use Filament\Resources\Pages\ListRecords;

class ListReconciliationRuns extends ListRecords
{
    protected static string $resource = ReconciliationRunResource::class;
}
