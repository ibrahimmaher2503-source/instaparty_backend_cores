<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources;

use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationPreference;
use App\Modules\Communication\Filament\Resources\NotificationPreferenceResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NotificationPreferenceResource extends Resource
{
    protected static ?string $model = NotificationPreference::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.communication');
    }

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {
        return __('communication.nav.preferences');
    }

    public static function getModelLabel(): string
    {
        return __('communication.models.notification_preference.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('communication.models.notification_preference.plural');
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user_id')
                    ->label(__('communication.columns.user_id'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('channel')
                    ->label(__('communication.columns.channel'))
                    ->badge()
                    ->formatStateUsing(fn (NotificationChannel $state): string => $state->label()),
                Tables\Columns\TextColumn::make('event_category')
                    ->label(__('communication.columns.event_category'))
                    ->badge()
                    ->formatStateUsing(fn (EventCategory $state): string => $state->label()),
                Tables\Columns\IconColumn::make('is_enabled')
                    ->label(__('communication.columns.is_enabled'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('quiet_hours_start')
                    ->label(__('communication.columns.quiet_hours_start')),
                Tables\Columns\TextColumn::make('quiet_hours_end')
                    ->label(__('communication.columns.quiet_hours_end')),
                Tables\Columns\TextColumn::make('timezone')
                    ->label(__('communication.columns.timezone')),
            ])
            ->defaultSort('user_id');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotificationPreferences::route('/'),
        ];
    }
}
