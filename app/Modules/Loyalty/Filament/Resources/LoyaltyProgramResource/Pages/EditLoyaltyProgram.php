<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Filament\Resources\LoyaltyProgramResource\Pages;

use App\Modules\Loyalty\Filament\Resources\LoyaltyProgramResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;

class EditLoyaltyProgram extends EditRecord
{
    use Translatable;

    protected static string $resource = LoyaltyProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
        ];
    }
}
