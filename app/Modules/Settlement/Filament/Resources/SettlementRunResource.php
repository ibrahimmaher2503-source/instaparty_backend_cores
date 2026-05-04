<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Resources;

use App\Modules\Settlement\Domain\Enums\SettlementRunStatus;
use App\Modules\Settlement\Domain\Models\SettlementRun;
use App\Modules\Settlement\Filament\Resources\SettlementRunResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class SettlementRunResource extends Resource
{
    protected static ?string $model = SettlementRun::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.settlement');
    }

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?int $navigationSort = 50;

    public static function getNavigationLabel(): string
    {
        return __('settlement.nav.settlement_runs');
    }

    public static function getModelLabel(): string
    {
        return __('settlement.models.settlement_run.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('settlement.models.settlement_run.plural');
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
                    ->label(__('settlement.columns.public_id'))
                    ->copyable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('period_start')
                    ->label(__('settlement.columns.period_start'))
                    ->date(),
                Tables\Columns\TextColumn::make('period_end')
                    ->label(__('settlement.columns.period_end'))
                    ->date(),
                Tables\Columns\TextColumn::make('total_gross_minor')
                    ->label(__('settlement.columns.total_gross'))
                    ->money('EGP', divideBy: 100),
                Tables\Columns\TextColumn::make('total_commission_minor')
                    ->label(__('settlement.columns.total_commission'))
                    ->money('EGP', divideBy: 100),
                Tables\Columns\TextColumn::make('total_vendor_share_minor')
                    ->label(__('settlement.columns.total_vendor_share'))
                    ->money('EGP', divideBy: 100),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('settlement.columns.status'))
                    ->badge()
                    ->formatStateUsing(fn (SettlementRunStatus $state): string => Str::headline($state->value)),
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
            'index' => Pages\ListSettlementRuns::route('/'),
        ];
    }
}
