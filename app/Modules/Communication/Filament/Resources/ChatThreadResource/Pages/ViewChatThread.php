<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources\ChatThreadResource\Pages;

use App\Modules\Communication\Filament\Resources\ChatThreadResource;
use Filament\Resources\Pages\ViewRecord;

class ViewChatThread extends ViewRecord
{
    protected static string $resource = ChatThreadResource::class;

    /**
     * SC-007 + FR-EXT-036-004: no Edit/Delete header actions ever appear on the read-only detail page.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
