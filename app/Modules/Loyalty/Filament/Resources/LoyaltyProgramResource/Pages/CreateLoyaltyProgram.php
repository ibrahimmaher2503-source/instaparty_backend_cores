<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Filament\Resources\LoyaltyProgramResource\Pages;

use App\Modules\Loyalty\Filament\Resources\LoyaltyProgramResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateLoyaltyProgram extends CreateRecord
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
