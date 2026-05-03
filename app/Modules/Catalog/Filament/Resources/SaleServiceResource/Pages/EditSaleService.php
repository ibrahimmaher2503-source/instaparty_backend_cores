<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\SaleServiceResource\Pages;

use App\Modules\Catalog\Filament\Resources\SaleServiceResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;

class EditSaleService extends EditRecord
{
    use Translatable;

    protected static string $resource = SaleServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
