<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Actions;

use App\Modules\Communication\Application\Actions\FreezeChatAction as FreezeChatApplicationAction;
use App\Modules\Communication\Application\DTOs\FreezeChatDTO;
use App\Modules\Communication\Domain\Enums\ChatFreezeCategory;
use App\Modules\Communication\Domain\Models\ChatThread;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

/**
 * Reusable Filament page-action (use on `ViewChatThread`) wrapping the
 * Application `FreezeChatAction`. Renders a modal with bilingual reason
 * inputs + category select; gated on `chat_moderation.freeze`.
 */
class FreezeChatAction
{
    public static function make(): Action
    {
        return Action::make('freezeChat')
            ->label(__('chat_moderation.actions.freeze'))
            ->icon('heroicon-o-lock-closed')
            ->color('danger')
            ->visible(fn (?ChatThread $record): bool => $record !== null
                && auth()->user()?->can('chat_moderation.freeze') === true
                && $record->frozen_at === null
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
                Select::make('category')
                    ->label(__('chat_moderation.fields.category'))
                    ->required()
                    ->options([
                        ChatFreezeCategory::OffPlatformContact->value => __('chat_moderation.categories.off_platform_contact'),
                        ChatFreezeCategory::PolicyViolation->value    => __('chat_moderation.categories.policy_violation'),
                        ChatFreezeCategory::Harassment->value         => __('chat_moderation.categories.harassment'),
                        ChatFreezeCategory::Other->value              => __('chat_moderation.categories.other'),
                    ]),
            ])
            ->requiresConfirmation()
            ->action(function (ChatThread $record, array $data): void {
                $dto = new FreezeChatDTO(
                    reasonEn: (string) $data['reason_en'],
                    reasonAr: (string) $data['reason_ar'],
                    category: ChatFreezeCategory::from((string) $data['category']),
                );

                app(FreezeChatApplicationAction::class)->execute($record, $dto, auth()->user());

                Notification::make()
                    ->title(__('chat_moderation.actions.freeze'))
                    ->success()
                    ->send();
            });
    }
}
