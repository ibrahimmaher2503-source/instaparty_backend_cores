<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Resources\FeatureFlagResource\Pages;

use App\Modules\Shared\Filament\Resources\FeatureFlagResource;
use Filament\Resources\Pages\ListRecords;

class ListFeatureFlags extends ListRecords
{
    protected static string $resource = FeatureFlagResource::class;
}
