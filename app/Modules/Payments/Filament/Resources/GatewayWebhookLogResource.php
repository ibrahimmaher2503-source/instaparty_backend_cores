<?php

declare(strict_types=1);

namespace App\Modules\Payments\Filament\Resources;

use App\Modules\Payments\Domain\Models\GatewayWebhookLog;
use App\Modules\Payments\Filament\Resources\GatewayWebhookLogResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class GatewayWebhookLogResource extends Resource
{
    protected static ?string $model = GatewayWebhookLog::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.payments');
    }

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return __('payments.navigation.webhook_logs');
    }

    public static function getModelLabel(): string
    {
        return __('payments.models.webhook_log.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('payments.models.webhook_log.plural');
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
                Tables\Columns\TextColumn::make('gateway')
                    ->label(__('payments.columns.gateway'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('event_type')
                    ->label(__('payments.columns.event_type'))
                    ->searchable()
                    ->formatStateUsing(function (?string $state): string {
                        if ($state === null || $state === '') {
                            return '';
                        }
                        $key = 'payments.event_types.'.str_replace('.', '_', $state);
                        $translated = __($key);

                        return $translated === $key ? Str::headline(str_replace('.', ' ', $state)) : $translated;
                    }),
                Tables\Columns\IconColumn::make('signature_valid')
                    ->label(__('payments.columns.signature_valid'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('processed_at')
                    ->label(__('payments.columns.processed_at'))
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
            'index' => Pages\ListGatewayWebhookLogs::route('/'),
        ];
    }
}
