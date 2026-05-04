<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Resources;

use App\Modules\Booking\Domain\Enums\ModificationProposalKind;
use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Booking\Filament\Resources\BookingModificationResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class BookingModificationResource extends Resource
{
    protected static ?string $model = BookingModification::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.booking');
    }

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {
        return __('booking.nav.modifications');
    }

    public static function getModelLabel(): string
    {
        return __('booking.models.modification.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('booking.models.modification.plural');
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
                Tables\Columns\TextColumn::make('booking_vendor_id')
                    ->label(__('booking.columns.booking_vendor'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('proposed_by')
                    ->label(__('booking.columns.proposed_by'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('proposal_kind')
                    ->label(__('booking.columns.proposal_kind'))
                    ->badge()
                    ->formatStateUsing(fn (ModificationProposalKind $state): string => Str::headline($state->value)),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('booking.columns.status'))
                    ->badge()
                    ->formatStateUsing(fn (ModificationStatus $state): string => Str::headline($state->value)),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('booking.columns.expires_at'))
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
            'index' => Pages\ListBookingModifications::route('/'),
        ];
    }
}
