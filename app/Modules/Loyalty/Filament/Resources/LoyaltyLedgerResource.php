<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Filament\Resources;

use App\Modules\Loyalty\Domain\Models\LoyaltyLedgerEntry;
use App\Modules\Loyalty\Filament\Resources\LoyaltyLedgerResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LoyaltyLedgerResource extends Resource
{
    protected static ?string $model = LoyaltyLedgerEntry::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.loyalty');
    }

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 40;

    public static function getNavigationLabel(): string
    {
        return __('loyalty.nav.ledger');
    }

    public static function getModelLabel(): string
    {
        return __('loyalty.models.ledger_entry.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('loyalty.models.ledger_entry.plural');
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
                Tables\Columns\TextColumn::make('entry_type')
                    ->label(__('loyalty.columns.entry_type'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => __('loyalty.reason.'.(is_object($state) ? $state->value : $state))),
                Tables\Columns\TextColumn::make('points')
                    ->label(__('loyalty.columns.points'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('reason')
                    ->label(__('loyalty.columns.reason'))
                    ->getStateUsing(fn (LoyaltyLedgerEntry $record): string => $record->getTranslation('reason', app()->getLocale(), useFallbackLocale: true))
                    ->limit(50),
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
            'index' => Pages\ListLoyaltyLedger::route('/'),
        ];
    }
}
