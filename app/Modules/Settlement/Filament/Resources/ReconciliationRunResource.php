<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Resources;

use App\Modules\Settlement\Domain\Enums\ReconciliationStatus;
use App\Modules\Settlement\Domain\Models\ReconciliationRun;
use App\Modules\Settlement\Filament\Resources\ReconciliationRunResource\Pages\ListReconciliationRuns;
use App\Modules\Settlement\Filament\Resources\ReconciliationRunResource\Pages\ViewReconciliationRun;
use App\Modules\Settlement\Filament\Resources\ReconciliationRunResource\RelationManagers\FindingsRelationManager;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReconciliationRunResource extends Resource
{
    protected static ?string $model = ReconciliationRun::class;

    protected static ?string $navigationIcon = 'heroicon-o-check-badge';

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.settlement');
    }

    public static function getNavigationLabel(): string
    {
        return __('settlement.nav.reconciliation_runs');
    }

    public static function getModelLabel(): string
    {
        return __('settlement.models.reconciliation_run.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('settlement.models.reconciliation_run.plural');
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
                    ->copyable(),

                Tables\Columns\TextColumn::make('scope_type')
                    ->badge()
                    ->label(__('settlement.columns.scope_type')),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (ReconciliationStatus $state): string => match ($state) {
                        ReconciliationStatus::Clean               => 'success',
                        ReconciliationStatus::Repaired            => 'warning',
                        ReconciliationStatus::RequiresManualReview => 'danger',
                        ReconciliationStatus::Running             => 'info',
                        ReconciliationStatus::Failed              => 'danger',
                        default                                   => 'gray',
                    })
                    ->label(__('settlement.columns.status')),

                Tables\Columns\TextColumn::make('wallets_scanned')
                    ->label(__('settlement.columns.wallets_scanned'))
                    ->numeric(),

                Tables\Columns\TextColumn::make('findings_count')
                    ->label(__('settlement.columns.findings_count'))
                    ->numeric(),

                Tables\Columns\TextColumn::make('manual_review_count')
                    ->label(__('settlement.columns.manual_review_count'))
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'success')
                    ->numeric(),

                Tables\Columns\TextColumn::make('started_at')
                    ->dateTime()
                    ->label(__('settlement.columns.started_at'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('completed_at')
                    ->dateTime()
                    ->label(__('settlement.columns.completed_at'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(ReconciliationStatus::class)
                    ->label(__('settlement.columns.status')),

                SelectFilter::make('scope_type')
                    ->options([
                        'all'          => 'All',
                        'wallet'       => 'Wallet',
                        'vendor'       => 'Vendor',
                        'date_range'   => 'Date Range',
                        'recent_touch' => 'Recent Touch',
                    ])
                    ->label(__('settlement.columns.scope_type')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make(__('settlement.reconciliation_run.summary'))
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('public_id')
                            ->label(__('settlement.columns.public_id'))
                            ->copyable(),

                        TextEntry::make('scope_type')
                            ->badge()
                            ->label(__('settlement.columns.scope_type')),

                        TextEntry::make('status')
                            ->badge()
                            ->label(__('settlement.columns.status')),

                        TextEntry::make('wallets_scanned')
                            ->label(__('settlement.columns.wallets_scanned')),

                        TextEntry::make('findings_count')
                            ->label(__('settlement.columns.findings_count')),

                        TextEntry::make('auto_repaired_count')
                            ->label(__('settlement.columns.auto_repaired_count')),

                        TextEntry::make('manual_review_count')
                            ->label(__('settlement.columns.manual_review_count')),

                        TextEntry::make('started_at')
                            ->dateTime()
                            ->label(__('settlement.columns.started_at')),

                        TextEntry::make('completed_at')
                            ->dateTime()
                            ->label(__('settlement.columns.completed_at')),
                    ]),
                ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            FindingsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReconciliationRuns::route('/'),
            'view'  => ViewReconciliationRun::route('/{record}'),
        ];
    }
}
