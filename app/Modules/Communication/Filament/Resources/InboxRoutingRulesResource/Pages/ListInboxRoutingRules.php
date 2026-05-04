<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources\InboxRoutingRulesResource\Pages;

use App\Modules\Communication\Filament\Resources\InboxRoutingRulesResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInboxRoutingRules extends ListRecords
{
    protected static string $resource = InboxRoutingRulesResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
