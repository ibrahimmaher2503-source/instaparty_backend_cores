<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Resources;

use App\Modules\Booking\Domain\Models\BookingStateTransition;
use App\Modules\Booking\Filament\Resources\BookingStateTransitionResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BookingStateTransitionResource extends Resource
{
    protected static ?string $model = BookingStateTransition::class;

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
