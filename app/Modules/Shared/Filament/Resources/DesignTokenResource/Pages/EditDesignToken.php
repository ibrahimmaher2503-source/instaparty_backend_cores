<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Resources\DesignTokenResource\Pages;

use App\Modules\Shared\Application\Actions\ActivateDesignTokenAction;
use App\Modules\Shared\Domain\Models\DesignToken;
use App\Modules\Shared\Filament\Resources\DesignTokenResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditDesignToken extends EditRecord
{
    protected static string $resource = DesignTokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('activate')
                ->label('Activate')
                ->icon('heroicon-o-bolt')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record instanceof DesignToken && ! $this->record->is_active)
                ->action(function (): void {
                    /** @var DesignToken $record */
                    $record = $this->record;
                    app(ActivateDesignTokenAction::class)->execute($record);
                    Notification::make()->title('Theme activated')->success()->send();
                    $this->refreshFormData(['is_active']);
                }),
            DeleteAction::make()
                ->visible(fn (): bool => $this->record instanceof DesignToken && ! $this->record->is_active),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var DesignToken $record */
        return DesignTokenResource::persistViaAction($data, $record);
    }
}
