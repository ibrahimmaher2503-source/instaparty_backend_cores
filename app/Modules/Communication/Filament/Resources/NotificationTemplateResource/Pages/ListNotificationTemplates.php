<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources\NotificationTemplateResource\Pages;

use App\Modules\Communication\Filament\Resources\NotificationTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;

class ListNotificationTemplates extends ListRecords
{
    use Translatable;

    protected static string $resource = NotificationTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
            Actions\CreateAction::make(),
        ];
    }
}
