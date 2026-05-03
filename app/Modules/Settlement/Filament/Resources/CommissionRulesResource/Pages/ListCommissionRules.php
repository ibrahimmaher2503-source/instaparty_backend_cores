<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Resources\CommissionRulesResource\Pages;

use App\Modules\Settlement\Filament\Resources\CommissionRulesResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCommissionRules extends ListRecords
{
    protected static string $resource = CommissionRulesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
