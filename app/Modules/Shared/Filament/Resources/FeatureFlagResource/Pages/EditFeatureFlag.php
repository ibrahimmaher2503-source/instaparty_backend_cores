<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Resources\FeatureFlagResource\Pages;

use App\Modules\Shared\Filament\Resources\FeatureFlagResource;
use Filament\Resources\Pages\EditRecord;

class EditFeatureFlag extends EditRecord
{
    protected static string $resource = FeatureFlagResource::class;
}
