<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources\CampaignResource\Pages;

use App\Modules\Communication\Filament\Resources\CampaignResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCampaigns extends ListRecords
{
    protected static string $resource = CampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
