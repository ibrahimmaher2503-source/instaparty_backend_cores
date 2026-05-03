<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources\NotificationTemplateResource\Pages;

use App\Modules\Communication\Filament\Resources\NotificationTemplateResource;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateNotificationTemplate extends CreateRecord
{
    use Translatable;

    protected static string $resource = NotificationTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
        ];
    }
}
