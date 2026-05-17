<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources\ChatMessageLogResource\Pages;

use App\Modules\Communication\Application\Actions\MarkOffPlatformContactAttemptAction;
use App\Modules\Communication\Application\DTOs\MarkOffPlatformContactDTO;
use App\Modules\Communication\Domain\Enums\ChatFlagType;
use App\Modules\Communication\Domain\Models\ChatMessageLog;
use App\Modules\Communication\Filament\Resources\ChatMessageLogResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewChatMessageLog extends ViewRecord
{
    protected static string $resource = ChatMessageLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markOffPlatform')
                ->label(__('chat_moderation.actions.mark_off_platform'))
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->visible(fn (): bool => auth()->user()?->can('chat_moderation.mark_off_platform') ?? false)
                ->form([
                    Select::make('flag_type')
                        ->label(__('chat_moderation.fields.flag_type'))
                        ->options([
                            'phone' => __('chat_moderation.flag_types.phone'),
                            'email' => __('chat_moderation.flag_types.email'),
                            'external_link' => __('chat_moderation.flag_types.external_link'),
                            'other' => __('chat_moderation.flag_types.other'),
                        ])
                        ->required(),
                    Textarea::make('reason_en')
                        ->label(__('chat_moderation.fields.reason_en'))
                        ->minLength(5)
                        ->maxLength(500)
                        ->required(),
                    Textarea::make('reason_ar')
                        ->label(__('chat_moderation.fields.reason_ar'))
                        ->minLength(5)
                        ->maxLength(500)
                        ->required(),
                ])
                ->action(function (ChatMessageLog $record, array $data): void {
                    app(MarkOffPlatformContactAttemptAction::class)->execute(
                        $record,
                        new MarkOffPlatformContactDTO(
                            chatMessageLogId: $record->id,
                            flagType: ChatFlagType::from($data['flag_type']),
                            reasonEn: $data['reason_en'],
                            reasonAr: $data['reason_ar'],
                        ),
                        auth()->user(),
                    );

                    Notification::make()
                        ->title(__('chat_moderation.notifications.marked_off_platform'))
                        ->success()
                        ->send();
                })
                ->requiresConfirmation(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make(__('chat_moderation.sections.message_details'))
                ->schema([
                    TextEntry::make('thread.public_id')
                        ->label(__('chat_moderation.columns.thread'))
                        ->placeholder('—'),
                    TextEntry::make('sender.name')
                        ->label(__('chat_moderation.columns.sender'))
                        ->default('<deleted user>'),
                    TextEntry::make('created_at')
                        ->label(__('chat_moderation.columns.created_at'))
                        ->dateTime(timezone: config('app.timezone', 'UTC')),
                    TextEntry::make('flagged')
                        ->label(__('chat_moderation.columns.flagged'))
                        ->badge()
                        ->color(fn ($state): string => $state ? 'warning' : 'gray'),
                    TextEntry::make('flag_reason')
                        ->label(__('chat_moderation.columns.flag_reason'))
                        ->placeholder('—'),
                    TextEntry::make('redacted')
                        ->label(__('chat_moderation.columns.redacted'))
                        ->badge()
                        ->color(fn ($state): string => $state ? 'danger' : 'gray'),
                    TextEntry::make('body_view')
                        ->label(__('chat_moderation.sections.message_body'))
                        ->getStateUsing(function (ChatMessageLog $record): string {
                            if ($record->redacted) {
                                return '<message redacted by moderation> / <تم حجب الرسالة بواسطة الإشراف>';
                            }

                            return '['.__('chat_moderation.firestore_placeholder').']';
                        })
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }
}
