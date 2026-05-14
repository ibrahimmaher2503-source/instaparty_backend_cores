<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Resources\BookingResource\RelationManagers;

use App\Modules\Booking\Domain\Models\BookingAddress;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BookingAddressesRelationManager extends RelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'address';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('booking.relations.addresses');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['city']))
            ->columns([
                Tables\Columns\TextColumn::make('city_id')
                    ->label(__('booking.columns.city'))
                    ->getStateUsing(fn (BookingAddress $record): string => self::resolveCityName($record))
                    ->searchable(),
                Tables\Columns\TextColumn::make('address_line')
                    ->label(__('booking.columns.address_line'))
                    ->wrap(),
                Tables\Columns\TextColumn::make('building')
                    ->label(__('booking.columns.building'))
                    ->default('—'),
                Tables\Columns\TextColumn::make('floor')
                    ->label(__('booking.columns.floor'))
                    ->default('—'),
                Tables\Columns\TextColumn::make('apartment')
                    ->label(__('booking.columns.apartment'))
                    ->default('—'),
                Tables\Columns\TextColumn::make('landmark')
                    ->label(__('booking.columns.landmark'))
                    ->wrap()
                    ->default('—'),
                Tables\Columns\TextColumn::make('recipient_name')
                    ->label(__('booking.columns.recipient_name')),
                Tables\Columns\TextColumn::make('recipient_phone_e164')
                    ->label(__('booking.columns.recipient_phone'))
                    ->copyable(),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading(__('booking.relations.addresses'))
            ->emptyStateDescription(__('booking.empty_states.addresses'))
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

    private static function resolveCityName(BookingAddress $record): string
    {
        $city = $record->city;
        if (! $city) {
            return '#'.$record->city_id;
        }

        $locale = app()->getLocale();
        $value = $city->getTranslation('name', $locale, false) ?: $city->getTranslation('name', 'en', false);

        while (is_array($value)) {
            $value = $value[$locale] ?? $value['en'] ?? reset($value);
        }

        return is_string($value) && $value !== '' ? $value : '#'.$record->city_id;
    }
}
