<?php

declare(strict_types=1);

namespace App\Modules\Tax\Filament\Resources\TaxRateResource\Pages;

use App\Modules\Tax\Filament\Resources\TaxRateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;

class EditTaxRate extends EditRecord
{
    use Translatable;

    protected static string $resource = TaxRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
