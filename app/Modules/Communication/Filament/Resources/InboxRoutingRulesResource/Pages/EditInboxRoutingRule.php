<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources\InboxRoutingRulesResource\Pages;

use App\Modules\Communication\Filament\Resources\InboxRoutingRulesResource;
use Filament\Resources\Pages\EditRecord;

class EditInboxRoutingRule extends EditRecord
{
    protected static string $resource = InboxRoutingRulesResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        return $data;
    }
}
