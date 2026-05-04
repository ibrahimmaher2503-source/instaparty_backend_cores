<?php

declare(strict_types=1);

namespace App\Modules\Payments\Filament\Resources;

use App\Modules\Payments\Domain\Models\PaymentAttempt;
use App\Modules\Payments\Filament\Resources\PaymentAttemptResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentAttemptResource extends Resource
{
    protected static ?string $model = PaymentAttempt::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.payments');
    }

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('payments.navigation.attempts');
    }

    public static function getModelLabel(): string
    {
        return __('payments.models.payment_attempt.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('payments.models.payment_attempt.plural');
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
                Tables\Columns\TextColumn::make('payment_id')
                    ->label(__('payments.columns.payment'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('attempt_no')
                    ->label(__('payments.columns.attempt_no'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('http_status')
                    ->label(__('payments.columns.http_status'))
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
            'index' => Pages\ListPaymentAttempts::route('/'),
        ];
    }
}
