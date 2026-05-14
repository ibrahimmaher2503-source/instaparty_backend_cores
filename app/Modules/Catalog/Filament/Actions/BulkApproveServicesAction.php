<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Actions;

use App\Modules\Catalog\Application\Actions\PublishServiceAction;
use App\Modules\Identity\Domain\Models\User;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

class BulkApproveServicesAction
{
    public static function make(): BulkAction
    {
        return BulkAction::make('approveSelected')
            ->label(__('catalog.approve_selected'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(__('catalog.approve_selected_heading'))
            ->modalDescription(__('catalog.approve_selected_description'))
            ->action(function (EloquentCollection $records): void {
                $count = self::execute($records, self::admin());

                Notification::make()
                    ->title(__('catalog.approve_selected_success', ['count' => $count]))
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
                app(PublishServiceAction::class)->execute($record, $admin);
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
