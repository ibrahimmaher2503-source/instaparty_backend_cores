<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Filament\Resources\SavedSearchResource\Pages;

use App\Modules\Discovery\Filament\Resources\SavedSearchResource;
use Filament\Resources\Pages\ListRecords;

class ListSavedSearches extends ListRecords
{
    protected static string $resource = SavedSearchResource::class;
}
