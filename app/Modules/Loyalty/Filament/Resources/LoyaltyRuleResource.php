<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Filament\Resources;

use App\Modules\Loyalty\Domain\Models\LoyaltyRule;
use App\Modules\Loyalty\Filament\Resources\LoyaltyRuleResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LoyaltyRuleResource extends Resource
{
    protected static ?string $model = LoyaltyRule::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.loyalty');
    }

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-vertical';

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('loyalty.nav.rules');
    }

    public static function getModelLabel(): string
    {
        return __('loyalty.models.rule.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('loyalty.models.rule.plural');
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
                Tables\Columns\TextColumn::make('loyalty_program_id')
                    ->label(__('loyalty.columns.program'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('label')
                    ->label(__('loyalty.columns.label'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('min_points_to_redeem')
                    ->label(__('loyalty.columns.min_points_to_redeem'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('max_redeem_pct_bps')
                    ->label(__('loyalty.columns.max_redeem_pct_bps'))
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('loyalty.columns.is_active'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('effective_from')
                    ->label(__('loyalty.columns.effective_from'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('effective_from', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoyaltyRules::route('/'),
        ];
    }
}
