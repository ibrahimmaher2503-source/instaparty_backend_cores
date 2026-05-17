<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Resources\WalletLedgerViewerResource\RelationManagers;

use App\Modules\Settlement\Infrastructure\Repositories\EloquentLedgerRepository;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;

class LedgerEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'ledgerEntries';

    protected static ?string $title = 'Ledger Entries';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('direction')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'credit' => 'success',
                        'debit'  => 'danger',
                        default  => 'gray',
                    })
                    ->label(__('settlement.columns.direction')),

                Tables\Columns\TextColumn::make('entry_type')
                    ->badge()
                    ->label(__('settlement.columns.entry_type')),

                Tables\Columns\TextColumn::make('amount_minor')
                    ->money('EGP', divideBy: 100)
                    ->label(__('settlement.columns.amount'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('running_balance_minor')
                    ->money('EGP', divideBy: 100)
                    ->label(__('settlement.columns.running_balance'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('transaction_group_id')
                    ->label(__('settlement.columns.group_id'))
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('correlation_id')
                    ->label(__('settlement.columns.correlation_id'))
                    ->copyable()
                    ->limit(26)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('causation_id')
                    ->label(__('settlement.columns.causation_id'))
                    ->copyable()
                    ->limit(26)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('related_entity_type')
                    ->label(__('settlement.columns.related_entity'))
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '—')
                    ->default('—'),

                Tables\Columns\TextColumn::make('related_entity_id')
                    ->label(__('settlement.columns.related_id'))
                    ->default('—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->label(__('settlement.created_at'))
                    ->sortable(),
            ])
            ->actions([
                Action::make('causalChain')
                    ->label(__('settlement.actions.show_causal_chain'))
                    ->icon('heroicon-o-arrows-right-left')
                    ->modalHeading(__('settlement.actions.causal_chain_title'))
                    ->infolist(function ($record): Infolist {
                        $chain = app(EloquentLedgerRepository::class)->causalChainFor($record->id);

                        return Infolist::make()
                            ->record($record)
                            ->schema([
                                Section::make(__('settlement.actions.causal_chain_entries', ['count' => $chain->count()]))
                                    ->schema(
                                        $chain->map(fn ($entry, $idx) => Grid::make(5)->schema([
                                            TextEntry::make("chain_{$idx}_direction")
                                                ->label(__('settlement.columns.direction'))
                                                ->state($entry->direction?->value ?? '—')
                                                ->badge()
                                                ->color(fn (string $state): string => $state === 'credit' ? 'success' : 'danger'),

                                            TextEntry::make("chain_{$idx}_entry_type")
                                                ->label(__('settlement.columns.entry_type'))
                                                ->state($entry->entry_type?->value ?? '—'),

                                            TextEntry::make("chain_{$idx}_amount")
                                                ->label(__('settlement.columns.amount'))
                                                ->state(number_format($entry->amount_minor / 100, 2) . ' EGP'),

                                            TextEntry::make("chain_{$idx}_correlation")
                                                ->label(__('settlement.columns.correlation_id'))
                                                ->state($entry->correlation_id ?? '—')
                                                ->copyable(),

                                            TextEntry::make("chain_{$idx}_causation")
                                                ->label(__('settlement.columns.causation_id'))
                                                ->state($entry->causation_id ?? '—')
                                                ->copyable(),
                                        ]))->values()->all()
                                    ),
                            ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('settlement.actions.close')),
            ])
            ->paginated([25, 50, 100]);
    }
}
