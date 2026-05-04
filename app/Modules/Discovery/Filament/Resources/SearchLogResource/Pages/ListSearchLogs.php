<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Filament\Resources\SearchLogResource\Pages;

use App\Modules\Discovery\Filament\Resources\SearchLogResource;
use Filament\Resources\Pages\ListRecords;

class ListSearchLogs extends ListRecords
{
    protected static string $resource = SearchLogResource::class;
}
