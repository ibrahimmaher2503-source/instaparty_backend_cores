<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources\ChatModerationFlagResource\Pages;

use App\Modules\Communication\Application\Actions\EscalateChatFlagToAdminInboxAction;
use App\Modules\Communication\Application\Actions\ResolveChatFlagAction;
use App\Modules\Communication\Application\DTOs\EscalateChatFlagDTO;
use App\Modules\Communication\Application\DTOs\ResolveChatFlagDTO;
use App\Modules\Communication\Domain\Enums\ChatFlagResolution;
use App\Modules\Communication\Domain\Models\ChatModerationFlag;
use App\Modules\Communication\Filament\Resources\ChatModerationFlagResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewChatModerationFlag extends ViewRecord
{
    protected static string $resource = ChatModerationFlagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resolveFlag')
                ->label(__('chat_moderation.actions.resolve_flag'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (ChatModerationFlag $record): bool => $record->reviewed_at === null
                    && auth()->user()?->can('chat_moderation.resolve_flag')
                )
                ->form([
                    Select::make('decision')
                        ->label(__('chat_moderation.fields.decision'))
                        ->options(collect(ChatFlagResolution::cases())->mapWithKeys(
                            fn (ChatFlagResolution $d) => [$d->value => __('chat_moderation.decisions.'.$d->value)]
                        ))
                        ->required(),
                    Textarea::make('note_en')
                        ->label(__('chat_moderation.fields.note_en'))
                        ->minLength(5)
                        ->maxLength(500)
                        ->required(),
                    Textarea::make('note_ar')
                        ->label(__('chat_moderation.fields.note_ar'))
                        ->minLength(5)
                        ->maxLength(500)
                        ->required(),
                ])
                ->action(function (ChatModerationFlag $record, array $data): void {
                    app(ResolveChatFlagAction::class)->execute(
                        $record,
                        new ResolveChatFlagDTO(
                            decision: ChatFlagResolution::from($data['decision']),
                            noteEn: $data['note_en'],
                            noteAr: $data['note_ar'],
                        ),
                        auth()->user(),
                    );

                    Notification::make()
                        ->title(__('chat_moderation.notifications.flag_resolved'))
                        ->success()
                        ->send();
                })
                ->requiresConfirmation(),

            Action::make('escalateToInbox')
                ->label(__('chat_moderation.actions.escalate'))
                ->icon('heroicon-o-arrow-up-on-square')
                ->color('warning')
                ->visible(fn (): bool => auth()->user()?->can('chat_moderation.escalate') ?? false)
                ->form([
                    Select::make('severity')
                        ->label(__('chat_moderation.fields.severity'))
                        ->options([
                            'info' => __('chat_moderation.severities.info'),
                            'warning' => __('chat_moderation.severities.warning'),
                            'critical' => __('chat_moderation.severities.critical'),
                        ])
                        ->required(),
                    Textarea::make('summary_en')
                        ->label(__('chat_moderation.fields.summary_en'))
                        ->minLength(5)
                        ->maxLength(280)
                        ->required(),
                    Textarea::make('summary_ar')
                        ->label(__('chat_moderation.fields.summary_ar'))
                        ->minLength(5)
                        ->maxLength(280)
                        ->required(),
                ])
                ->action(function (ChatModerationFlag $record, array $data): void {
                    app(EscalateChatFlagToAdminInboxAction::class)->execute(
                        $record,
                        new EscalateChatFlagDTO(
                            chatModerationFlagId: $record->id,
                            severity: $data['severity'],
                            summaryEn: $data['summary_en'],
                            summaryAr: $data['summary_ar'],
                        ),
                        auth()->user(),
                    );

                    Notification::make()
                        ->title(__('chat_moderation.notifications.flag_escalated'))
                        ->success()
                        ->send();
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make(__('chat_moderation.sections.flag_details'))
                ->schema([
                    TextEntry::make('flag_type')
                        ->label(__('chat_moderation.columns.flag_type'))
                        ->badge(),
                    TextEntry::make('matched_pattern')
                        ->label(__('chat_moderation.columns.matched_pattern'))
                        ->placeholder('—'),
                    TextEntry::make('action_taken')
                        ->label(__('chat_moderation.columns.action_taken'))
                        ->badge(),
                    TextEntry::make('created_at')
                        ->label(__('chat_moderation.columns.created_at'))
                        ->dateTime(timezone: config('app.timezone', 'UTC')),
                    TextEntry::make('reviewed_at')
                        ->label(__('chat_moderation.columns.reviewed_at'))
                        ->dateTime(timezone: config('app.timezone', 'UTC'))
                        ->placeholder(__('chat_moderation.unresolved')),
                    TextEntry::make('reviewer.name')
                        ->label(__('chat_moderation.columns.reviewer'))
                        ->placeholder('—'),
                ])
                ->columns(2),
        ]);
    }
}
