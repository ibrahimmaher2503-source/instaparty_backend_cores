<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\VendorProfileResource\RelationManagers;

use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class WalletRelationManager extends RelationManager
{
    protected static string $relationship = 'wallet';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('identity.sections.wallet');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make()->schema([
                TextEntry::make('balance_minor')
                    ->money('EGP', divideBy: 100)
                    ->label(__('settlement.balance')),
                TextEntry::make('currency')
                    ->label(__('settlement.currency')),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->label(__('shared.updated_at')),
            ])->columns(3),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('balance_minor')
                    ->money('EGP', divideBy: 100)
                    ->label(__('settlement.balance')),
                Tables\Columns\TextColumn::make('currency')
                    ->label(__('settlement.currency')),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->label(__('shared.updated_at')),
            ]);
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
