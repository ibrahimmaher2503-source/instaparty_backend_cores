<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Actions;

use App\Modules\Catalog\Application\Actions\ArchiveServiceAction;
use App\Modules\Identity\Domain\Models\User;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

class BulkArchiveServicesAction
{
    public static function make(): BulkAction
    {
        return BulkAction::make('archiveSelected')
            ->label(__('catalog.archive_selected'))
            ->icon('heroicon-o-archive-box')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(__('catalog.archive_selected_heading'))
            ->modalDescription(__('catalog.archive_selected_description'))
            ->action(function (EloquentCollection $records): void {
                $count = self::execute($records, self::admin());

                Notification::make()
                    ->title(__('catalog.archive_selected_success', ['count' => $count]))
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function execute(iterable $records, User $admin): int
    {
        return DB::transaction(function () use ($records, $admin): int {
            $processed = 0;

            foreach ($records as $record) {
                app(ArchiveServiceAction::class)->execute($record, $admin);
                $processed++;
            }

            return $processed;
        });
    }

    private static function admin(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
