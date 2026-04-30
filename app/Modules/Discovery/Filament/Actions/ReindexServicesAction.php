<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Filament\Actions;

use App\Modules\Catalog\Domain\Models\Service;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;

class ReindexServicesAction
{
    public static function make(): Action
    {
        return Action::make('reindex')
            ->label(__('discovery.reindex_services'))
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->requiresConfirmation()
            ->action(function (): void {
                Service::published()->searchable();
                Notification::make()
                    ->title(__('discovery.reindex_success'))
                    ->success()
                    ->send();
            })
            ->visible(fn (): bool => auth()->user()?->can('manage_search_index') ?? false);
    }
}
