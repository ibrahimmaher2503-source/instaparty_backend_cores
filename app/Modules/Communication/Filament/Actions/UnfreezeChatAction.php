<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Actions;

use App\Modules\Communication\Application\Actions\UnfreezeChatAction as UnfreezeChatApplicationAction;
use App\Modules\Communication\Application\DTOs\UnfreezeChatDTO;
use App\Modules\Communication\Domain\Exceptions\ChatThreadUnfreezeForbidden;
use App\Modules\Communication\Domain\Models\ChatThread;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

/**
 * Reusable Filament page-action (use on `ViewChatThread`) wrapping the
 * Application `UnfreezeChatAction`. Visible only when the thread is currently
 * frozen; gated on `chat_moderation.unfreeze`.
 */
class UnfreezeChatAction
{
    public static function make(): Action
    {
        return Action::make('unfreezeChat')
            ->label(__('chat_moderation.actions.unfreeze'))
            ->icon('heroicon-o-lock-open')
            ->color('warning')
            ->visible(fn (?ChatThread $record): bool => $record !== null
                && auth()->user()?->can('chat_moderation.unfreeze') === true
                && $record->frozen_at !== null
            )
            ->form([
                Textarea::make('reason_en')
                    ->label(__('chat_moderation.fields.reason_en'))
                    ->required()
                    ->minLength(5)
                    ->maxLength(500)
                    ->rows(3),
                Textarea::make('reason_ar')
                    ->label(__('chat_moderation.fields.reason_ar'))
                    ->required()
                    ->minLength(5)
                    ->maxLength(500)
                    ->rows(3),
            ])
            ->requiresConfirmation()
            ->action(function (ChatThread $record, array $data): void {
                $dto = new UnfreezeChatDTO(
                    reasonEn: (string) $data['reason_en'],
                    reasonAr: (string) $data['reason_ar'],
                );

                try {
                    app(UnfreezeChatApplicationAction::class)->execute($record, $dto, auth()->user());

                    Notification::make()
                        ->title(__('chat_moderation.actions.unfreeze'))
                        ->success()
                        ->send();
                } catch (ChatThreadUnfreezeForbidden $e) {
                    Notification::make()
                        ->title(__('chat_moderation.errors.unfreeze_forbidden_outside_window'))
                        ->danger()
                        ->send();
                }
            });
    }
}
