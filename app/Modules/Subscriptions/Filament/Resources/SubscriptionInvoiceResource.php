<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Filament\Resources;

use App\Modules\Subscriptions\Domain\Enums\InvoiceStatus;
use App\Modules\Subscriptions\Domain\Models\SubscriptionInvoice;
use App\Modules\Subscriptions\Filament\Resources\SubscriptionInvoiceResource\Pages;
use App\Modules\Subscriptions\Filament\Resources\SubscriptionInvoiceResource\RelationManagers\SubscriptionPaymentsRelationManager;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SubscriptionInvoiceResource extends Resource
{
    protected static ?string $model = SubscriptionInvoice::class;

    protected static ?string $navigationGroup = 'subscriptions';

    protected static ?string $navigationLabel = 'Invoices';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'public_id';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('vendorSubscription.vendorProfile.business_name')
                    ->label(__('subscription.vendor'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('vendorSubscription.plan.plan_code')
                    ->label('Plan')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'free' => 'gray',
                        'silver' => 'info',
                        'gold' => 'warning',
                        'premium' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('amount_minor')
                    ->label('Amount')
                    ->money('EGP', divideBy: 100)
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state->value ?? $state) {
                        InvoiceStatus::Pending->value, InvoiceStatus::Pending => 'warning',
                        InvoiceStatus::Paid->value, InvoiceStatus::Paid => 'success',
                        InvoiceStatus::Failed->value, InvoiceStatus::Failed => 'danger',
                        InvoiceStatus::Refunded->value, InvoiceStatus::Refunded => 'info',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('period_start')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('period_end')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(InvoiceStatus::class),

                Tables\Filters\SelectFilter::make('vendorSubscription')
                    ->relationship('vendorSubscription', 'public_id')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            SubscriptionPaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptionInvoices::route('/'),
            'view' => Pages\ViewSubscriptionInvoice::route('/{record}'),
        ];
    }

    public static function getHeaderActions(): array
    {
        return [];
    }
}
