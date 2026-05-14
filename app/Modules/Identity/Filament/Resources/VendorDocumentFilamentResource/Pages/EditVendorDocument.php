<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\VendorDocumentFilamentResource\Pages;

use App\Modules\Identity\Filament\Resources\VendorDocumentFilamentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVendorDocument extends EditRecord
{
    protected static string $resource = VendorDocumentFilamentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
