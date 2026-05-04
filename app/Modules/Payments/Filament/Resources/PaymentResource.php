<?php

declare(strict_types=1);

namespace App\Modules\Payments\Filament\Resources;

use App\Modules\Payments\Domain\Enums\PaymentMethod;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Filament\Resources\PaymentResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.payments');
    }

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('payments.navigation.payments');
    }

    public static function getModelLabel(): string
    {
        return __('payments.models.payment.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('payments.models.payment.plural');
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['booking']);
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
                    ->label(__('payments.columns.public_id'))
                    ->copyable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('booking.reference_no')
                    ->label(__('payments.columns.booking'))
                    ->searchable()
                    ->sortable()
                    ->default(fn (Payment $record): string => '#'.$record->booking_id),
                Tables\Columns\TextColumn::make('gateway')
                    ->label(__('payments.columns.gateway'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount_minor')
                    ->label(__('payments.columns.amount'))
                    ->money('EGP', divideBy: 100)
                    ->sortable(),
                Tables\Columns\TextColumn::make('method')
                    ->label(__('payments.columns.method'))
                    ->badge()
                    ->formatStateUsing(fn (PaymentMethod $state): string => Str::headline($state->value)),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('payments.columns.status'))
                    ->badge()
                    ->formatStateUsing(fn (PaymentStatus $state): string => Str::headline($state->value)),
                Tables\Columns\TextColumn::make('captured_at')
                    ->label(__('payments.columns.captured_at'))
                    ->dateTime()
                    ->sortable(),
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
            'index' => Pages\ListPayments::route('/'),
        ];
    }
}
