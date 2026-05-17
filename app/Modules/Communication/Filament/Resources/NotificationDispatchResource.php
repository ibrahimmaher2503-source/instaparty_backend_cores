<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources;

use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Filament\Resources\NotificationDispatchResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NotificationDispatchResource extends Resource
{
    protected static ?string $model = NotificationDispatch::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.communication');
    }

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('communication.nav.dispatches');
    }

    public static function getModelLabel(): string
    {
        return __('communication.models.notification_dispatch.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('communication.models.notification_dispatch.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['template']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('public_id')
                    ->label(__('communication.columns.public_id'))
                    ->copyable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('template.event_key')
                    ->label(__('communication.columns.template'))
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('channel')
                    ->label(__('communication.columns.channel'))
                    ->badge()
                    ->formatStateUsing(fn (NotificationChannel $state): string => $state->label()),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('communication.columns.status'))
                    ->badge()
                    ->formatStateUsing(fn (DispatchStatus $state): string => $state->label()),
                Tables\Columns\TextColumn::make('locale')
                    ->label(__('communication.columns.locale'))
                    ->badge(),
                Tables\Columns\TextColumn::make('provider')
                    ->label(__('communication.columns.provider')),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotificationDispatches::route('/'),
        ];
    }
}
