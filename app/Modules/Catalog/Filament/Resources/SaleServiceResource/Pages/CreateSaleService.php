<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\SaleServiceResource\Pages;

use App\Modules\Catalog\Filament\Resources\SaleServiceResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateSaleService extends CreateRecord
{
    use Translatable;

    protected static string $resource = SaleServiceResource::class;
}
