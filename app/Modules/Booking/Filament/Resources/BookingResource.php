<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Resources;

use App\Modules\Booking\Domain\Enums\FulfillmentStatus;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\PaymentStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Filament\Resources\BookingResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.booking');
    }

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('booking.nav.bookings');
    }

    public static function getModelLabel(): string
    {
        return __('booking.models.booking.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('booking.models.booking.plural');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['customer', 'occasion'])
            ->withCount(['vendors', 'items', 'snapshots']);
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
                    ->label(__('booking.columns.public_id'))
                    ->copyable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('reference_no')
                    ->label(__('booking.columns.reference_no'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('booking.columns.customer_id'))
                    ->searchable()
                    ->sortable()
                    ->default(fn (Booking $record): string => '#'.$record->customer_id),
                Tables\Columns\TextColumn::make('occasion_id')
                    ->label(__('booking.columns.occasion_id'))
                    ->sortable()
                    ->getStateUsing(function (Booking $record): string {
                        $occasion = $record->occasion;
                        if (! $occasion) {
                            return '#'.$record->occasion_id;
                        }
                        $locale = app()->getLocale();
                        $value = $occasion->getTranslation('name', $locale, false)
                            ?: $occasion->getTranslation('name', 'en', false);
                        while (is_array($value)) {
                            $value = $value[$locale] ?? $value['en'] ?? reset($value);
                        }
                        return is_string($value) && $value !== '' ? $value : '#'.$record->occasion_id;
                    }),
                Tables\Columns\TextColumn::make('lifecycle_status')
                    ->label(__('booking.columns.lifecycle_status'))
                    ->badge()
                    ->formatStateUsing(fn (LifecycleStatus $state): string => __('booking.lifecycle_status.'.$state->value)),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label(__('booking.columns.payment_status'))
                    ->badge()
                    ->formatStateUsing(fn (PaymentStatus $state): string => __('booking.payment_status.'.$state->value)),
                Tables\Columns\TextColumn::make('fulfillment_status')
                    ->label(__('booking.columns.fulfillment_status'))
                    ->badge()
                    ->formatStateUsing(fn (FulfillmentStatus $state): string => __('booking.fulfillment_status.'.$state->value)),
                Tables\Columns\TextColumn::make('total_minor')
                    ->label(__('booking.columns.total'))
                    ->money('EGP', divideBy: 100)
                    ->sortable(),
                Tables\Columns\TextColumn::make('submitted_at')
                    ->label(__('booking.columns.submitted_at'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookings::route('/'),
        ];
    }
}
