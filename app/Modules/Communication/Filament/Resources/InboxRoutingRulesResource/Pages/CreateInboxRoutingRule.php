<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources\InboxRoutingRulesResource\Pages;

use App\Modules\Communication\Filament\Resources\InboxRoutingRulesResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateInboxRoutingRule extends CreateRecord
{
    protected static string $resource = InboxRoutingRulesResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['public_id'] = Str::ulid()->toBase32();
        $data['created_by'] = auth()->id();

        return $data;
    }
}
