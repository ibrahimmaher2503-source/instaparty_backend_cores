<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Resources;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Filament\Resources\BookingStateTransitionResource\Pages;
use App\Modules\Shared\Domain\Models\StateTransition;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookingStateTransitionResource extends Resource
{
    protected static ?string $model = StateTransition::class;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('transitionable_type', [
                Booking::class,
                BookingVendor::class,
                BookingItem::class,
            ]);
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.booking');
    }

    protected static ?string $navigationIcon = 'heroicon-o-arrows-up-down';

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('booking.nav.state_transitions');
    }

    public static function getModelLabel(): string
    {
        return __('booking.models.state_transition.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('booking.models.state_transition.plural');
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
                Tables\Columns\TextColumn::make('transitionable_type')
                    ->label(__('booking.columns.transitionable_type'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('transitionable_id')
                    ->label(__('booking.columns.transitionable_id'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('from_state')
                    ->label(__('booking.columns.from_state'))
                    ->badge(),
                Tables\Columns\TextColumn::make('to_state')
                    ->label(__('booking.columns.to_state'))
                    ->badge(),
                Tables\Columns\TextColumn::make('triggered_by')
                    ->label(__('booking.columns.triggered_by'))
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
            'index' => Pages\ListBookingStateTransitions::route('/'),
        ];
    }
}
