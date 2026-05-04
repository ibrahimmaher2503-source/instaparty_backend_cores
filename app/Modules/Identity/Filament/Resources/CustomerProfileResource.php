<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources;

use App\Modules\Identity\Domain\Models\CustomerProfile;
use App\Modules\Identity\Filament\Resources\CustomerProfileResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerProfileResource extends Resource
{
    protected static ?string $model = CustomerProfile::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.identity');
    }

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('identity.nav.customer_profiles');
    }

    public static function getModelLabel(): string
    {
        return __('identity.models.customer_profile.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('identity.models.customer_profile.plural');
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
                    ->label(__('identity.columns.user_id'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('date_of_birth')
                    ->label(__('identity.columns.date_of_birth'))
                    ->date(),
                Tables\Columns\TextColumn::make('gender')
                    ->label(__('identity.columns.gender'))
                    ->badge(),
                Tables\Columns\IconColumn::make('accepts_marketing')
                    ->label(__('identity.columns.accepts_marketing'))
                    ->boolean(),
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
            'index' => Pages\ListCustomerProfiles::route('/'),
        ];
    }
}
