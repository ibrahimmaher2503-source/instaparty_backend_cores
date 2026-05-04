<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources;

use App\Modules\Identity\Domain\Models\CustomerAddress;
use App\Modules\Identity\Filament\Resources\CustomerAddressResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerAddressResource extends Resource
{
    protected static ?string $model = CustomerAddress::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.identity');
    }

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('identity.nav.customer_addresses');
    }

    public static function getModelLabel(): string
    {
        return __('identity.models.customer_address.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('identity.models.customer_address.plural');
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
                Tables\Columns\TextColumn::make('user_id')
                    ->label(__('identity.columns.user_id'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('city_id')
                    ->label(__('identity.columns.city_id'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('label')
                    ->label(__('identity.columns.label'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('recipient_name')
                    ->label(__('identity.columns.recipient_name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('recipient_phone_e164')
                    ->label(__('identity.columns.recipient_phone'))
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_default')
                    ->label(__('identity.columns.is_default'))
                    ->boolean(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomerAddresses::route('/'),
        ];
    }
}
