<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\VendorProfileResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class WithdrawalsRelationManager extends RelationManager
{
    protected static string $relationship = 'withdrawals';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('identity.sections.withdrawals');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('public_id')
                    ->label('ID')
                    ->copyable()
                    ->limit(13),
                Tables\Columns\TextColumn::make('requested_amount_minor')
                    ->money('EGP', divideBy: 100)
                    ->label(__('settlement.requested_amount')),
                Tables\Columns\TextColumn::make('paid_amount_minor')
                    ->money('EGP', divideBy: 100)
                    ->label(__('settlement.paid_amount')),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->label(__('settlement.status')),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label(__('shared.created_at')),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public function canCreate(): bool
    {
        return false;
    }

    public function canEdit(Model $record): bool
    {
        return false;
    }

    public function canDelete(Model $record): bool
    {
        return false;
    }
}
