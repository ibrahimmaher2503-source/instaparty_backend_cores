<?php

declare(strict_types=1);

namespace App\Modules\Payments\Filament\Resources;

use App\Modules\Payments\Domain\Models\IdempotencyKey;
use App\Modules\Payments\Filament\Resources\IdempotencyKeyResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class IdempotencyKeyResource extends Resource
{
    protected static ?string $model = IdempotencyKey::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.payments');
    }

    protected static ?string $navigationIcon = 'heroicon-o-finger-print';

    protected static ?int $navigationSort = 40;

    public static function getNavigationLabel(): string
    {
        return __('payments.navigation.idempotency_keys');
    }

    public static function getModelLabel(): string
    {
        return __('payments.models.idempotency_key.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('payments.models.idempotency_key.plural');
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
                Tables\Columns\TextColumn::make('key')
                    ->label(__('payments.columns.key'))
                    ->searchable()
                    ->limit(60),
                Tables\Columns\TextColumn::make('route')
                    ->label(__('payments.columns.route'))
                    ->searchable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('response_status')
                    ->label(__('payments.columns.response_status'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('payments.columns.expires_at'))
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
            'index' => Pages\ListIdempotencyKeys::route('/'),
        ];
    }
}
