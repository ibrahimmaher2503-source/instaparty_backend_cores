<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Resources\BookingResource\RelationManagers;

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\BookingVendor;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BookingVendorsRelationManager extends RelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'vendors';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('booking.relations.vendors');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['vendor']))
            ->columns([
                Tables\Columns\TextColumn::make('vendor_profile_id')
                    ->label(__('booking.columns.vendor'))
                    ->getStateUsing(fn (BookingVendor $record): string => self::resolveVendorName($record))
                    ->searchable(),
                Tables\Columns\TextColumn::make('sub_status')
                    ->label(__('booking.columns.vendor_status'))
                    ->badge()
                    ->color(fn (VendorSubStatus $state): string => match ($state) {
                        VendorSubStatus::Pending => 'warning',
                        VendorSubStatus::Accepted, VendorSubStatus::InProgress => 'info',
                        VendorSubStatus::Completed => 'success',
                        VendorSubStatus::Modified => 'warning',
                        VendorSubStatus::Rejected, VendorSubStatus::Cancelled => 'danger',
                        VendorSubStatus::TimedOut => 'gray',
                    })
                    ->formatStateUsing(fn (VendorSubStatus $state): string => __('booking.vendor_status.'.$state->value)),
                Tables\Columns\TextColumn::make('response_deadline')
                    ->label(__('booking.columns.response_deadline'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('vendor_payout_minor')
                    ->label(__('booking.columns.total'))
                    ->money('EGP', divideBy: 100)
                    ->sortable(),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading(__('booking.relations.vendors'))
            ->emptyStateDescription(__('booking.empty_states.vendors'))
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

    private static function resolveVendorName(BookingVendor $record): string
    {
        $vendor = $record->vendor;
        if (! $vendor) {
            return '#'.$record->vendor_profile_id;
        }

        $locale = app()->getLocale();
        $value = $vendor->getTranslation('business_name', $locale, false) ?: $vendor->getTranslation('business_name', 'en', false);

        while (is_array($value)) {
            $value = $value[$locale] ?? $value['en'] ?? reset($value);
        }

        return is_string($value) && $value !== '' ? $value : '#'.$record->vendor_profile_id;
    }
}
