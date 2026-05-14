<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Actions;

use App\Modules\Catalog\Application\Actions\PublishServiceAction;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Policies\ServicePolicy;
use App\Modules\Identity\Domain\Models\User;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;

class ApproveServiceAction
{
    public static function make(): Action
    {
        return Action::make('approvePublish')
            ->label(__('catalog.approve_publish'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(__('catalog.approve_publish_heading'))
            ->modalDescription(__('catalog.approve_publish_description'))
            ->action(function (Service $record): void {
                app(PublishServiceAction::class)->execute($record, self::admin());

                Notification::make()
                    ->title(__('catalog.approve_publish_success'))
                    ->success()
                    ->send();
            })
            ->visible(function (Service $record): bool {
                $user = auth()->user();

                return $user instanceof User
                    && app(ServicePolicy::class)->approve($user, $record);
            });
    }

    private static function admin(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
