<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Filament\Resources;

use App\Modules\Loyalty\Domain\Models\LoyaltyRedemption;
use App\Modules\Loyalty\Domain\States\Redemption\RedemptionState;
use App\Modules\Loyalty\Filament\Resources\LoyaltyRedemptionResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LoyaltyRedemptionResource extends Resource
{
    protected static ?string $model = LoyaltyRedemption::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.loyalty');
    }

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return __('loyalty.nav.redemptions');
    }

    public static function getModelLabel(): string
    {
        return __('loyalty.models.redemption.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('loyalty.models.redemption.plural');
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
                    ->label(__('loyalty.columns.public_id'))
                    ->copyable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('customer_id')
                    ->label(__('loyalty.columns.customer'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('vendor_profile_id')
                    ->label(__('loyalty.columns.vendor'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('points_held')
                    ->label(__('loyalty.columns.points_held'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('discount_minor')
                    ->label(__('loyalty.columns.discount_minor'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('loyalty.columns.status'))
                    ->badge()
                    ->formatStateUsing(fn (RedemptionState $state): string => class_basename($state)),
                Tables\Columns\TextColumn::make('applied_at')
                    ->label(__('loyalty.columns.applied_at'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('voided_at')
                    ->label(__('loyalty.columns.voided_at'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reversed_at')
                    ->label(__('loyalty.columns.reversed_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoyaltyRedemptions::route('/'),
        ];
    }
}
