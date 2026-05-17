<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources\ChatMessageLogResource\Pages;

use App\Modules\Communication\Domain\Models\ChatMessageLog;
use App\Modules\Communication\Filament\Resources\ChatMessageLogResource;
use App\Modules\Communication\Filament\Resources\ChatThreadResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListChatMessageLogs extends ListRecords
{
    protected static string $resource = ChatMessageLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ChatMessageLog::query()
                    ->with(['thread', 'sender'])
            )
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('thread.public_id')
                    ->label(__('chat_moderation.columns.thread'))
                    ->limit(20)
                    ->url(fn (ChatMessageLog $record): ?string => $record->thread
                        ? ChatThreadResource::getUrl('view', ['record' => $record->thread])
                        : null
                    ),

                TextColumn::make('sender.name')
                    ->label(__('chat_moderation.columns.sender'))
                    ->default('<deleted user>'),

                TextColumn::make('created_at')
                    ->label(__('chat_moderation.columns.created_at'))
                    ->dateTime(timezone: config('app.timezone', 'UTC')),

                IconColumn::make('flagged')
                    ->label(__('chat_moderation.columns.flagged'))
                    ->boolean(),

                IconColumn::make('redacted')
                    ->label(__('chat_moderation.columns.redacted'))
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('flagged')
                    ->label(__('chat_moderation.columns.flagged'))
                    ->queries(
                        true: fn (Builder $q) => $q->where('flagged', true),
                        false: fn (Builder $q) => $q->where('flagged', false),
                        blank: fn (Builder $q) => $q,
                    ),
                TernaryFilter::make('redacted')
                    ->label(__('chat_moderation.columns.redacted'))
                    ->queries(
                        true: fn (Builder $q) => $q->where('redacted', true),
                        false: fn (Builder $q) => $q->where('redacted', false),
                        blank: fn (Builder $q) => $q,
                    ),
            ])
            ->actions([
                ViewAction::make(),
            ]);
    }
}
