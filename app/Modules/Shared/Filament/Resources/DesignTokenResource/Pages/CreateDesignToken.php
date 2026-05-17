<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Resources\DesignTokenResource\Pages;

use App\Modules\Shared\Domain\Models\DesignToken;
use App\Modules\Shared\Filament\Resources\DesignTokenResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateDesignToken extends CreateRecord
{
    protected static string $resource = DesignTokenResource::class;

    public function mount(): void
    {
        parent::mount();

        $this->form->fill([
            'name' => '',
            'tokens' => DesignTokenResource::defaultTokens(),
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        return DesignTokenResource::persistViaAction($data, null);
    }

    protected function getRedirectUrl(): string
    {
        /** @var DesignToken $record */
        $record = $this->record;

        return static::getResource()::getUrl('edit', ['record' => $record]);
    }
}
