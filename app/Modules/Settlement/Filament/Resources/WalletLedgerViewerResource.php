<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Resources;

use App\Modules\Settlement\Domain\Models\Wallet;
use App\Modules\Settlement\Filament\Resources\WalletLedgerViewerResource\Pages\ListWalletLedger;
use App\Modules\Settlement\Filament\Resources\WalletLedgerViewerResource\Pages\ViewWalletLedger;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WalletLedgerViewerResource extends Resource
{
    protected static ?string $model = Wallet::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.settlement');
    }

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $recordTitleAttribute = 'public_id';

    protected static ?string $slug = 'settlement-wallets';

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('settlement.nav.wallet_ledger');
    }

    public static function getModelLabel(): string
    {
        return __('settlement.models.wallet.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('settlement.models.wallet.plural');
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

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Wallet Details')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('public_id')
                                ->label('Reference')
                                ->copyable(),

                            TextEntry::make('owner_type')
                                ->label('Owner Type'),

                            TextEntry::make('owner_id')
                                ->label('Owner ID'),

                            TextEntry::make('currency')
                                ->label('Currency'),

                            TextEntry::make('balance_minor')
                                ->label('Balance')
                                ->money('EGP', divideBy: 100),

                            TextEntry::make('pending_withdrawal_minor')
                                ->label('Pending Withdrawal')
                                ->money('EGP', divideBy: 100),

                            TextEntry::make('updated_at')
                                ->label('Last Updated')
                                ->dateTime(),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('public_id')
                    ->label('Reference')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('owner_type')
                    ->label('Owner Type')
                    ->formatStateUsing(fn (string $state): string => class_basename($state)),

                Tables\Columns\TextColumn::make('owner_id')
                    ->label('Owner ID')
                    ->searchable(),

                Tables\Columns\TextColumn::make('balance_minor')
                    ->money('EGP', divideBy: 100)
                    ->label('Balance')
                    ->sortable(),

                Tables\Columns\TextColumn::make('pending_withdrawal_minor')
                    ->money('EGP', divideBy: 100)
                    ->label('Pending Withdrawal'),

                Tables\Columns\TextColumn::make('currency')
                    ->label('Currency'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->label('Last Updated')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            WalletLedgerViewerResource\RelationManagers\LedgerEntriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWalletLedger::route('/'),
            'view' => ViewWalletLedger::route('/{record}'),
        ];
    }
}
