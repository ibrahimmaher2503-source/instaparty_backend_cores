<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Filament\Resources\LoyaltyProgramResource\Pages;

use App\Modules\Loyalty\Filament\Resources\LoyaltyProgramResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;

class ListLoyaltyPrograms extends ListRecords
{
    use Translatable;

    protected static string $resource = LoyaltyProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\LocaleSwitcher::make(),
        ];
    }
}
