<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Resources\DesignTokenResource\Pages;

use App\Modules\Shared\Filament\Resources\DesignTokenResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDesignTokens extends ListRecords
{
    protected static string $resource = DesignTokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
