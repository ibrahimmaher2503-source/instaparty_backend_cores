<?php

declare(strict_types=1);

namespace App\Modules\Tax\Filament\Resources\TaxRateResource\Pages;

use App\Modules\Tax\Filament\Resources\TaxRateResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateTaxRate extends CreateRecord
{
    use Translatable;

    protected static string $resource = TaxRateResource::class;
}
