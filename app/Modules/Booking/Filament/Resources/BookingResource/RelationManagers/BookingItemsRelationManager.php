<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Resources\BookingResource\RelationManagers;

use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Catalog\Domain\Enums\ProductType;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BookingItemsRelationManager extends RelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'items';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('booking.relations.items');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['service']))
            ->columns([
                Tables\Columns\TextColumn::make('service_id')
                    ->label(__('booking.columns.service'))
                    ->getStateUsing(fn (BookingItem $record): string => self::resolveServiceName($record))
                    ->searchable(),
                Tables\Columns\TextColumn::make('product_type')
                    ->label(__('booking.columns.product_type'))
                    ->badge()
                    ->color(fn (ProductType $state): string => match ($state) {
                        ProductType::Rental => 'info',
                        ProductType::Sale => 'success',
                        ProductType::Digital => 'warning',
                    })
                    ->formatStateUsing(fn (ProductType $state): string => Str::headline($state->value)),
                Tables\Columns\TextColumn::make('quantity')
                    ->label(__('booking.columns.quantity'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('line_total_minor')
                    ->label(__('booking.columns.line_total'))
                    ->money('EGP', divideBy: 100)
                    ->sortable(),
                Tables\Columns\TextColumn::make('item_status')
                    ->label(__('booking.columns.item_status'))
                    ->badge()
                    ->color(fn (string $state): string => self::resolveItemStatusColor($state))
                    ->formatStateUsing(fn (string $state): string => Str::headline(str_replace('_', ' ', $state))),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading(__('booking.relations.items'))
            ->emptyStateDescription(__('booking.empty_states.items'))
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

    private static function resolveServiceName(BookingItem $record): string
    {
        $service = $record->service;
        if (! $service) {
            return '#'.$record->service_id;
        }

        $locale = app()->getLocale();
        $value = $service->getTranslation('name', $locale, false) ?: $service->getTranslation('name', 'en', false);

        while (is_array($value)) {
            $value = $value[$locale] ?? $value['en'] ?? reset($value);
        }

        return is_string($value) && $value !== '' ? $value : '#'.$record->service_id;
    }

    private static function resolveItemStatusColor(string $state): string
    {
        return match ($state) {
            'pending', 'pending_delivery' => 'warning',
            'out_for_delivery', 'in_preparation', 'sent', 'ready' => 'info',
            'delivered', 'setup_complete', 'redeemed', 'picked_up', 'completed' => 'success',
            'cancelled', 'failed' => 'danger',
            default => 'gray',
        };
    }
}
