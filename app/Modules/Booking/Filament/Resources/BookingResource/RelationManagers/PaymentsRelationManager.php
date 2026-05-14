<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Resources\BookingResource\RelationManagers;

use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Filament\Resources\PaymentResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PaymentsRelationManager extends RelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'payments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('booking.relations.payments');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Payment $record): string => PaymentResource::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('public_id')
                    ->label(__('payments.columns.public_id'))
                    ->copyable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('gateway')
                    ->label(__('payments.columns.gateway'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount_minor')
                    ->label(__('payments.columns.amount'))
                    ->money('EGP', divideBy: 100)
                    ->sortable(),
                Tables\Columns\TextColumn::make('method')
                    ->label(__('payments.columns.method'))
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('payments.columns.status'))
                    ->badge(),
                Tables\Columns\TextColumn::make('captured_at')
                    ->label(__('payments.columns.captured_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading(__('booking.relations.payments'))
            ->emptyStateDescription(__('booking.empty_states.payments'))
            ->defaultSort('created_at', 'desc');
    }
}
