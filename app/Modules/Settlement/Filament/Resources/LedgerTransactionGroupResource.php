<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Resources;

use App\Modules\Settlement\Domain\Enums\TransactionKind;
use App\Modules\Settlement\Domain\Models\LedgerTransactionGroup;
use App\Modules\Settlement\Filament\Resources\LedgerTransactionGroupResource\Pages\ListLedgerTransactionGroups;
use App\Modules\Settlement\Filament\Resources\LedgerTransactionGroupResource\Pages\ViewLedgerTransactionGroup;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LedgerTransactionGroupResource extends Resource
{
    protected static ?string $model = LedgerTransactionGroup::class;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?int $navigationSort = 25;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.settlement');
    }

    public static function getNavigationLabel(): string
    {
        return __('settlement.nav.ledger_groups');
    }

    public static function getModelLabel(): string
    {
        return __('settlement.models.ledger_group.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('settlement.models.ledger_group.plural');
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
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('public_id')
                    ->label(__('settlement.columns.public_id'))
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('kind')
                    ->badge()
                    ->color(fn (TransactionKind $state): string => match ($state) {
                        TransactionKind::PaymentCapture      => 'success',
                        TransactionKind::CommissionAccrual   => 'info',
                        TransactionKind::Refund              => 'danger',
                        TransactionKind::CommissionReversal  => 'warning',
                        TransactionKind::WithdrawalReserve   => 'gray',
                        TransactionKind::WithdrawalSettle    => 'success',
                        TransactionKind::WithdrawalRejectRelease => 'warning',
                        default                              => 'gray',
                    })
                    ->label(__('settlement.columns.kind')),

                Tables\Columns\TextColumn::make('currency')
                    ->label(__('settlement.columns.currency')),

                Tables\Columns\TextColumn::make('correlation_id')
                    ->label(__('settlement.columns.correlation_id'))
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->label(__('settlement.created_at'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('kind')
                    ->options(TransactionKind::class)
                    ->label(__('settlement.columns.kind')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make(__('settlement.ledger_group.detail'))
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('public_id')
                                ->label(__('settlement.columns.public_id'))
                                ->copyable(),

                            TextEntry::make('kind')
                                ->badge()
                                ->label(__('settlement.columns.kind')),

                            TextEntry::make('currency')
                                ->label(__('settlement.columns.currency')),

                            TextEntry::make('correlation_id')
                                ->label(__('settlement.columns.correlation_id'))
                                ->copyable(),

                            TextEntry::make('causation_id')
                                ->label(__('settlement.columns.causation_id'))
                                ->copyable(),

                            TextEntry::make('initiator_type')
                                ->label(__('settlement.columns.initiator_type')),

                            TextEntry::make('created_at')
                                ->dateTime()
                                ->label(__('settlement.created_at')),
                        ]),
                ]),

            Section::make(__('settlement.ledger_group.entries'))
                ->schema([
                    RepeatableEntry::make('entries')
                        ->schema([
                            Grid::make(4)
                                ->schema([
                                    TextEntry::make('direction')
                                        ->badge()
                                        ->color(fn (string $state): string => $state === 'credit' ? 'success' : 'danger')
                                        ->label(__('settlement.columns.direction')),

                                    TextEntry::make('amount_minor')
                                        ->money('EGP', divideBy: 100)
                                        ->label(__('settlement.columns.amount')),

                                    TextEntry::make('entry_type')
                                        ->badge()
                                        ->label(__('settlement.columns.entry_type')),

                                    TextEntry::make('running_balance_minor')
                                        ->money('EGP', divideBy: 100)
                                        ->label(__('settlement.columns.running_balance')),
                                ]),
                        ])
                        ->label(''),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLedgerTransactionGroups::route('/'),
            'view'  => ViewLedgerTransactionGroup::route('/{record}'),
        ];
    }
}
