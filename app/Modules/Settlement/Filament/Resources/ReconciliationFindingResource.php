<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Resources;

use App\Modules\Settlement\Domain\Enums\ReconciliationFindingSeverity;
use App\Modules\Settlement\Domain\Enums\ReconciliationFindingType;
use App\Modules\Settlement\Domain\Models\ReconciliationFinding;
use App\Modules\Settlement\Filament\Resources\ReconciliationFindingResource\Pages\ListReconciliationFindings;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReconciliationFindingResource extends Resource
{
    protected static ?string $model = ReconciliationFinding::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?int $navigationSort = 31;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.settlement');
    }

    public static function getNavigationLabel(): string
    {
        return __('settlement.nav.reconciliation_findings');
    }

    public static function getModelLabel(): string
    {
        return __('settlement.models.reconciliation_finding.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('settlement.models.reconciliation_finding.plural');
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

                Tables\Columns\TextColumn::make('run.public_id')
                    ->label(__('settlement.columns.run_id'))
                    ->searchable()
                    ->url(fn (ReconciliationFinding $record): string =>
                        ReconciliationRunResource::getUrl('view', ['record' => $record->reconciliation_run_id])
                    ),

                Tables\Columns\TextColumn::make('finding_type')
                    ->badge()
                    ->color(fn (ReconciliationFindingType $state): string => match ($state) {
                        ReconciliationFindingType::WalletCacheDrift              => 'warning',
                        ReconciliationFindingType::OrphanedRefundRow             => 'danger',
                        ReconciliationFindingType::OrphanedLedgerEntry           => 'danger',
                        ReconciliationFindingType::UnbalancedTransactionGroup    => 'danger',
                        ReconciliationFindingType::CommissionWithoutSnapshotRate => 'danger',
                        ReconciliationFindingType::WithdrawalWithoutReserveEntry => 'danger',
                        ReconciliationFindingType::NegativeVendorBalance         => 'danger',
                        ReconciliationFindingType::CurrencyMismatch              => 'danger',
                    })
                    ->label(__('settlement.columns.finding_type')),

                Tables\Columns\TextColumn::make('severity')
                    ->badge()
                    ->color(fn (ReconciliationFindingSeverity $state): string => match ($state) {
                        ReconciliationFindingSeverity::Info    => 'info',
                        ReconciliationFindingSeverity::Warning => 'warning',
                        ReconciliationFindingSeverity::High    => 'danger',
                    })
                    ->label(__('settlement.columns.severity')),

                Tables\Columns\TextColumn::make('resource_type')
                    ->label(__('settlement.columns.resource_type'))
                    ->limit(30),

                Tables\Columns\TextColumn::make('resource_id')
                    ->label(__('settlement.columns.resource_id'))
                    ->numeric(),

                Tables\Columns\TextColumn::make('resolution')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'auto_repaired' => 'success',
                        'ignored'       => 'gray',
                        default         => 'warning',
                    })
                    ->default(__('settlement.findings.unresolved'))
                    ->label(__('settlement.columns.resolution')),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label(__('settlement.columns.detected_at')),
            ])
            ->filters([
                SelectFilter::make('severity')
                    ->options(ReconciliationFindingSeverity::class)
                    ->label(__('settlement.columns.severity')),

                SelectFilter::make('finding_type')
                    ->options(ReconciliationFindingType::class)
                    ->label(__('settlement.columns.finding_type')),

                SelectFilter::make('resolution')
                    ->options([
                        'auto_repaired' => __('settlement.findings.auto_repaired'),
                        'ignored'       => __('settlement.findings.ignored'),
                    ])
                    ->label(__('settlement.columns.resolution')),
            ])
            ->actions([
                Tables\Actions\Action::make('markIgnored')
                    ->label(__('settlement.actions.mark_ignored'))
                    ->icon('heroicon-o-eye-slash')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading(__('settlement.actions.mark_ignored_heading'))
                    ->modalDescription(__('settlement.actions.mark_ignored_description'))
                    ->form([
                        Textarea::make('reason')
                            ->label(__('settlement.actions.ignore_reason'))
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (ReconciliationFinding $record, array $data): void {
                        $record->update([
                            'resolution'          => 'ignored',
                            'resolved_by_user_id' => auth()->id(),
                            'resolved_at'         => now(),
                        ]);

                        Notification::make()
                            ->title(__('settlement.actions.marked_ignored_success'))
                            ->success()
                            ->send();
                    })
                    ->visible(fn (ReconciliationFinding $record): bool => $record->resolution === null),
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReconciliationFindings::route('/'),
        ];
    }
}
