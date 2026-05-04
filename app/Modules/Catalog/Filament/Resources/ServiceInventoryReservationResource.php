<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources;

use App\Modules\Catalog\Domain\Enums\HoldType;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ReservationStatus;
use App\Modules\Catalog\Domain\Models\ServiceInventoryReservation;
use App\Modules\Catalog\Filament\Resources\ServiceInventoryReservationResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ServiceInventoryReservationResource extends Resource
{
    protected static ?string $model = ServiceInventoryReservation::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.services');
    }

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('catalog.nav.inventory_reservations');
    }

    public static function getModelLabel(): string
    {
        return __('catalog.models.inventory_reservation.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('catalog.models.inventory_reservation.plural');
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
                    ->label(__('catalog.public_id'))
                    ->copyable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('service.public_id')
                    ->label(__('catalog.service'))
                    ->copyable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('user_id')
                    ->label(__('catalog.user_id'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('product_type')
                    ->label(__('catalog.product_type'))
                    ->badge()
                    ->formatStateUsing(fn (ProductType $state): string => $state->label()),
                Tables\Columns\TextColumn::make('hold_type')
                    ->label(__('catalog.hold_type'))
                    ->badge()
                    ->formatStateUsing(fn (HoldType $state): string => __('catalog.hold_types.'.$state->value)),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('catalog.status_label'))
                    ->badge()
                    ->formatStateUsing(fn (ReservationStatus $state): string => $state->label()),
                Tables\Columns\TextColumn::make('quantity')
                    ->label(__('catalog.quantity'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('reserved_starts_at')
                    ->label(__('catalog.reserved_starts_at'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reserved_ends_at')
                    ->label(__('catalog.reserved_ends_at'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('catalog.expires_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('product_type')
                    ->label(__('catalog.product_type'))
                    ->options(collect(ProductType::cases())->mapWithKeys(fn (ProductType $type): array => [$type->value => $type->label()])),
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('catalog.status_label'))
                    ->options([
                        ReservationStatus::Held->value => __('catalog.reservation_status_held'),
                        ReservationStatus::Confirmed->value => __('catalog.reservation_status_confirmed'),
                        ReservationStatus::Expired->value => __('catalog.reservation_status_expired'),
                        ReservationStatus::Released->value => __('catalog.reservation_status_released'),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServiceInventoryReservations::route('/'),
        ];
    }
}
