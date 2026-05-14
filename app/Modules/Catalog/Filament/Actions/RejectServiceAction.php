<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Actions;

use App\Modules\Catalog\Application\Actions\RejectServiceAction as RejectServiceApplicationAction;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Policies\ServicePolicy;
use App\Modules\Identity\Domain\Models\User;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;

class RejectServiceAction
{
    public static function make(): Action
    {
        return Action::make('rejectService')
            ->label(__('catalog.reject_service'))
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
            ->modalHeading(__('catalog.reject_service_heading'))
            ->modalDescription(__('catalog.reject_service_description'))
            ->action(function (Service $record, array $data): void {
                app(RejectServiceApplicationAction::class)->execute($record, $data['reason'] ?? [], self::admin());

                Notification::make()
                    ->title(__('catalog.reject_success'))
                    ->success()
                    ->send();
            })
            ->visible(function (Service $record): bool {
                $user = auth()->user();

                return $user instanceof User
                    && app(ServicePolicy::class)->reject($user, $record);
            });
    }

    private static function admin(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
