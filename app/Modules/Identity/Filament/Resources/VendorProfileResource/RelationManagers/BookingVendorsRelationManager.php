<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\VendorProfileResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class BookingVendorsRelationManager extends RelationManager
{
    protected static string $relationship = 'bookingVendors';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('identity.sections.bookings');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('booking.public_id')
                    ->label('Booking ID')
                    ->copyable()
                    ->limit(13),
                Tables\Columns\TextColumn::make('sub_status')
                    ->badge()
                    ->label(__('booking.sub_status')),
                Tables\Columns\TextColumn::make('booking.lifecycle_status')
                    ->badge()
                    ->label(__('booking.lifecycle_status')),
                Tables\Columns\TextColumn::make('vendor_total_minor')
                    ->money('EGP', divideBy: 100)
                    ->label(__('booking.vendor_total')),
                Tables\Columns\TextColumn::make('booking.created_at')
                    ->dateTime()
                    ->sortable()
                    ->label(__('shared.created_at')),
            ])
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
}
