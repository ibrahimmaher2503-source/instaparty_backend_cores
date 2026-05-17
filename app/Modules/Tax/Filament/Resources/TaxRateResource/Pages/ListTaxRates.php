<?php

declare(strict_types=1);

namespace App\Modules\Tax\Filament\Resources\TaxRateResource\Pages;

use App\Modules\Tax\Filament\Resources\TaxRateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;

class ListTaxRates extends ListRecords
{
    use Translatable;

    protected static string $resource = TaxRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
            Actions\CreateAction::make(),
        ];
    }
}
