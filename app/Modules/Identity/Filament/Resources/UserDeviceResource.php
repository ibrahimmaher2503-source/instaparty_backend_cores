<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources;

use App\Modules\Identity\Domain\Models\UserDevice;
use App\Modules\Identity\Filament\Resources\UserDeviceResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserDeviceResource extends Resource
{
    protected static ?string $model = UserDevice::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.identity');
    }

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return __('identity.nav.devices');
    }

    public static function getModelLabel(): string
    {
        return __('identity.models.user_device.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('identity.models.user_device.plural');
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
        return parent::getEloquentQuery()->with(['user']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('identity.columns.user_id'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('platform')
                    ->label(__('identity.columns.platform'))
                    ->badge(),
                Tables\Columns\TextColumn::make('device_id')
                    ->label(__('identity.columns.device_id'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('last_seen_at')
                    ->label(__('identity.columns.last_seen_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('last_seen_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUserDevices::route('/'),
        ];
    }
}
