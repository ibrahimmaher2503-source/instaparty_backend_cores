<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Resources;

use App\Modules\Booking\Application\Actions\ForceCancelBookingAction;
use App\Modules\Booking\Application\DTOs\AdminInterventionDTO;
use App\Modules\Booking\Domain\Enums\InterventionType;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Filament\Resources\BookingsMonitorResource\Pages\ListBookingsMonitor;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookingsMonitorResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?string $navigationGroup = 'Booking';

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'bookings-monitor';

    public static function getNavigationLabel(): string
    {
        return __('booking.nav.negotiation_monitor');
    }

    public static function getModelLabel(): string
    {
        return __('booking.models.negotiation_monitor.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('booking.models.negotiation_monitor.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

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
                    ->label(__('booking.columns.reference_no')),

                Tables\Columns\TextColumn::make('customer_id')
                    ->label(__('booking.columns.customer_id'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('lifecycle_status')
                    ->badge()
                    ->color(fn (LifecycleStatus $state): string => match ($state) {
                        LifecycleStatus::VendorReview => 'warning',
                        LifecycleStatus::CustomerReview => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (LifecycleStatus $state): string => __('booking.lifecycle_status.'.$state->value))
                    ->label(__('booking.columns.lifecycle_status')),

                Tables\Columns\TextColumn::make('total_minor')
                    ->money('EGP', divideBy: 100)
                    ->sortable()
                    ->label(__('booking.columns.total')),

                Tables\Columns\TextColumn::make('submitted_at')
                    ->dateTime()
                    ->sortable()
                    ->label(__('booking.columns.submitted_at')),

                Tables\Columns\TextColumn::make('vendors_min_deadline')
                    ->label(__('booking.columns.nearest_deadline'))
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
                        LifecycleStatus::VendorReview->value => __('booking.lifecycle_status.vendor_review'),
                        LifecycleStatus::CustomerReview->value => __('booking.lifecycle_status.customer_review'),
                    ])
                    ->label(__('booking.columns.lifecycle_status')),
            ])
            ->actions([
                Action::make('forceCancel')
                    ->label(__('booking.force_cancel'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('booking.force_cancel_confirm_heading'))
                    ->modalDescription(__('booking.force_cancel_confirm_description'))
                    ->form([
                        Textarea::make('reason')
                            ->label(__('booking.force_cancel_reason'))
                            ->required()
                            ->minLength(10)
                            ->maxLength(1000),
                    ])
                    ->visible(fn (Booking $record): bool =>
                        auth()->user()?->can('force_cancel_booking') === true
                        && $record->lifecycle_status !== LifecycleStatus::Completed
                    )
                    ->action(function (Booking $record, array $data): void {
                        app(ForceCancelBookingAction::class)->execute(
                            $record,
                            new AdminInterventionDTO(
                                bookingId: $record->id,
                                adminId: (int) auth()->id(),
                                interventionType: InterventionType::ForceCancel,
                                reason: $data['reason'],
                            ),
                        );

                        Notification::make()
                            ->title(__('booking.force_cancelled_successfully'))
                            ->success()
                            ->send();
                    }),
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
