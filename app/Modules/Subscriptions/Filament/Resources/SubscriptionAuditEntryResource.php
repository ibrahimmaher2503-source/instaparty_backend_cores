<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Filament\Resources;

use App\Modules\Subscriptions\Domain\Enums\SubscriptionEventType;
use App\Modules\Subscriptions\Domain\Models\SubscriptionAuditEntry;
use App\Modules\Subscriptions\Filament\Resources\SubscriptionAuditEntryResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SubscriptionAuditEntryResource extends Resource
{
    protected static ?string $model = SubscriptionAuditEntry::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.subscriptions');
    }

    protected static ?string $navigationLabel = 'Audit Log';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'public_id';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('actor_type')
                    ->label('Actor')
                    ->formatStateUsing(fn ($record) => "{$record->actor_type} #{$record->actor_id}")
                    ->sortable(),

                Tables\Columns\TextColumn::make('event_type')
                    ->badge()
                    ->color(fn ($state) => match ($state->value ?? $state) {
                        SubscriptionEventType::AdminOverrideApplied->value, SubscriptionEventType::AdminOverrideApplied => 'warning',
                        SubscriptionEventType::AdminOverrideEnded->value, SubscriptionEventType::AdminOverrideEnded => 'info',
                        SubscriptionEventType::Expired->value, SubscriptionEventType::Expired => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('vendorSubscription.vendorProfile.business_name')
                    ->label(__('subscription.vendor'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_type')
                    ->options(SubscriptionEventType::class),

                Tables\Filters\SelectFilter::make('vendor_profile_id')
                    ->label('Vendor')
                    ->relationship('vendorSubscription.vendorProfile', 'business_name->en')
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptionAuditEntries::route('/'),
            'view' => Pages\ViewSubscriptionAuditEntry::route('/{record}'),
        ];
    }

    public static function getHeaderActions(): array
    {
        return [];
    }
}
