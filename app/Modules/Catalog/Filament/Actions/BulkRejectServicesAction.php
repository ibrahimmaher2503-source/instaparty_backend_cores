<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Actions;

use App\Modules\Catalog\Application\Actions\RejectServiceAction as RejectServiceApplicationAction;
use App\Modules\Identity\Domain\Models\User;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

class BulkRejectServicesAction
{
    public static function make(): BulkAction
    {
        return BulkAction::make('rejectSelected')
            ->label(__('catalog.reject_selected'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->form([
                Textarea::make('reason.en')
                    ->label(__('catalog.reject_reason_en'))
                    ->required()
                    ->maxLength(1000)
                    ->rows(3),
                Textarea::make('reason.ar')
                    ->label(__('catalog.reject_reason_ar'))
                    ->required()
                    ->maxLength(1000)
                    ->rows(3)
                    ->extraInputAttributes(['dir' => 'rtl']),
            ])
            ->requiresConfirmation()
            ->modalHeading(__('catalog.reject_selected_heading'))
            ->modalDescription(__('catalog.reject_selected_description'))
            ->action(function (EloquentCollection $records, array $data): void {
                $count = self::execute($records, $data['reason'] ?? [], self::admin());

                Notification::make()
                    ->title(__('catalog.reject_selected_success', ['count' => $count]))
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    /**
     * @param  array{en?: string|null, ar?: string|null}  $notes
     */
    public static function execute(iterable $records, array $notes, User $admin): int
    {
        return DB::transaction(function () use ($records, $notes, $admin): int {
            $processed = 0;

            foreach ($records as $record) {
                app(RejectServiceApplicationAction::class)->execute($record, $notes, $admin);
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
