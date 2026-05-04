<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Filament\Resources\UserResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.identity');
    }

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('identity.nav.users');
    }

    public static function getModelLabel(): string
    {
        return __('identity.models.user.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('identity.models.user.plural');
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
                Tables\Columns\TextColumn::make('public_id')
                    ->label(__('identity.columns.id'))
                    ->copyable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('identity.columns.name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label(__('identity.columns.email'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone_e164')
                    ->label(__('identity.columns.phone'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('identity.columns.status'))
                    ->badge(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label(__('identity.columns.role'))
                    ->badge()
                    ->listWithLineBreaks()
                    ->formatStateUsing(function (?string $state): string {
                        if ($state === null || $state === '') {
                            return '';
                        }
                        $key = 'identity.roles.'.str_replace('.', '_', $state);
                        $translated = __($key);

                        return $translated === $key ? Str::headline(str_replace('.', ' ', $state)) : $translated;
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('identity.columns.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
        ];
    }
}
