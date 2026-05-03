<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Resources\WalletLedgerViewerResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LedgerEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'ledgerEntries';

    protected static ?string $title = 'Ledger Entries';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('entry_type')
                    ->badge()
                    ->label('Type'),

                Tables\Columns\TextColumn::make('amount_minor')
                    ->money('EGP', divideBy: 100)
                    ->label('Amount')
                    ->sortable(),

                Tables\Columns\TextColumn::make('currency')
                    ->label('Currency'),

                Tables\Columns\TextColumn::make('description_key')
                    ->label('Description')
                    ->limit(50),

                Tables\Columns\TextColumn::make('related_entity_type')
                    ->label('Related Entity')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '—')
                    ->default('—'),

                Tables\Columns\TextColumn::make('related_entity_id')
                    ->label('Related ID')
                    ->default('—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->label('Created At')
                    ->sortable(),
            ])
            ->paginated([25, 50, 100]);
    }
}
