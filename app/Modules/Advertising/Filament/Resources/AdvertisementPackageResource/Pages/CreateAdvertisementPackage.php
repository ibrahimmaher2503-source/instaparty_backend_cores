<?php

declare(strict_types=1);

namespace App\Modules\Advertising\Filament\Resources\AdvertisementPackageResource\Pages;

use App\Modules\Advertising\Filament\Resources\AdvertisementPackageResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateAdvertisementPackage extends CreateRecord
{
    use Translatable;

    protected static string $resource = AdvertisementPackageResource::class;
}
