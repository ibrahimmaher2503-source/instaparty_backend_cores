<?php

declare(strict_types=1);

namespace App\Modules\Advertising\Filament\Resources\AdvertisementPackageResource\Pages;

use App\Modules\Advertising\Filament\Resources\AdvertisementPackageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;

class ListAdvertisementPackages extends ListRecords
{
    use Translatable;

    protected static string $resource = AdvertisementPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
            Actions\CreateAction::make(),
        ];
    }
}
