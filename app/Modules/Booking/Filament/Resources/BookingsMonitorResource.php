<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Resources;

use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Filament\Resources\BookingsMonitorResource\Pages\ListBookingsMonitor;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookingsMonitorResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?string $navigationGroup = 'Bookings';

    protected static ?string $navigationLabel = 'Negotiation Monitor';

    protected static ?string $pluralModelLabel = 'Negotiation Monitor';

    protected static ?string $slug = 'bookings-monitor';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('lifecycle_status', [
                LifecycleStatus::VendorReview->value,
                LifecycleStatus::CustomerReview->value,
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_no')
                    ->searchable()
                    ->sortable()
                    ->label('Reference'),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable(),

                Tables\Columns\TextColumn::make('lifecycle_status')
                    ->badge()
                    ->color(fn (LifecycleStatus $state): string => match ($state) {
                        LifecycleStatus::VendorReview => 'warning',
                        LifecycleStatus::CustomerReview => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (LifecycleStatus $state) => $state->value)
                    ->label('Status'),

                Tables\Columns\TextColumn::make('total_minor')
                    ->money('EGP', divideBy: 100)
                    ->sortable()
                    ->label('Total'),

                Tables\Columns\TextColumn::make('submitted_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Submitted'),

                Tables\Columns\TextColumn::make('vendors_min_deadline')
                    ->label('Nearest Deadline')
                    ->dateTime()
                    ->sortable()
                    ->getStateUsing(fn (Booking $record): ?string => $record->vendors()
                        ->whereNotNull('response_deadline')
                        ->min('response_deadline')
                    ),
            ])
            ->filters([
                SelectFilter::make('lifecycle_status')
                    ->options([
                        LifecycleStatus::VendorReview->value => 'Vendor Review',
                        LifecycleStatus::CustomerReview->value => 'Customer Review',
                    ])
                    ->label('Status'),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->paginated([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBookingsMonitor::route('/'),
        ];
    }
}
