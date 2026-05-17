<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources\ChatModerationFlagResource\Pages;

use App\Modules\Communication\Domain\Enums\ChatFlagAction;
use App\Modules\Communication\Domain\Enums\ChatFlagType;
use App\Modules\Communication\Domain\Models\ChatModerationFlag;
use App\Modules\Communication\Filament\Resources\ChatModerationFlagResource;
use App\Modules\Communication\Filament\Resources\ChatThreadResource;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListChatModerationFlags extends ListRecords
{
    protected static string $resource = ChatModerationFlagResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ChatModerationFlag::query()
                    ->with(['messageLog.thread', 'reviewer'])
            )
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('messageLog.thread.public_id')
                    ->label(__('chat_moderation.columns.thread'))
                    ->limit(20)
                    ->url(fn (ChatModerationFlag $record): ?string => $record->messageLog?->thread
                        ? ChatThreadResource::getUrl('view', ['record' => $record->messageLog->thread])
                        : null
                    )
                    ->placeholder('—'),

                TextColumn::make('flag_type')
                    ->label(__('chat_moderation.columns.flag_type'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => __('chat_moderation.flag_types.'.($state instanceof ChatFlagType ? $state->value : $state)))
                    ->color(fn ($state): string => match ($state instanceof ChatFlagType ? $state->value : $state) {
                        'phone' => 'danger',
                        'email' => 'warning',
                        'external_link' => 'info',
                        'profanity' => 'gray',
                        'other' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('matched_pattern')
                    ->label(__('chat_moderation.columns.matched_pattern'))
                    ->limit(30)
                    ->placeholder('—'),

                TextColumn::make('action_taken')
                    ->label(__('chat_moderation.columns.action_taken'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => __('chat_moderation.actions_taken.'.($state instanceof ChatFlagAction ? $state->value : $state))),

                TextColumn::make('created_at')
                    ->label(__('chat_moderation.columns.created_at'))
                    ->dateTime(timezone: config('app.timezone', 'UTC')),

                TextColumn::make('reviewed_at')
                    ->label(__('chat_moderation.columns.reviewed_at'))
                    ->dateTime(timezone: config('app.timezone', 'UTC'))
                    ->placeholder(__('chat_moderation.unresolved'))
                    ->color(fn ($state): string => $state ? 'success' : 'warning'),

                TextColumn::make('reviewer.name')
                    ->label(__('chat_moderation.columns.reviewer'))
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('flag_type')
                    ->label(__('chat_moderation.columns.flag_type'))
                    ->options(collect(ChatFlagType::cases())->mapWithKeys(
                        fn (ChatFlagType $t) => [$t->value => __('chat_moderation.flag_types.'.$t->value)]
                    )),

                TernaryFilter::make('reviewed')
                    ->label(__('chat_moderation.filters.reviewed'))
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('reviewed_at'),
                        false: fn (Builder $q) => $q->whereNull('reviewed_at'),
                        blank: fn (Builder $q) => $q,
                    ),

                Filter::make('created_range')
                    ->label(__('chat_moderation.columns.created_at'))
                    ->form([
                        DatePicker::make('from')->label(__('chat_moderation.filters.created_from')),
                        DatePicker::make('to')->label(__('chat_moderation.filters.created_to')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['to'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d));
                    }),
            ])
            ->actions([
                ViewAction::make(),
            ]);
    }
}
