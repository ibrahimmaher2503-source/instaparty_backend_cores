<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Resources;

use App\Modules\Booking\Domain\Enums\FulfillmentStatus;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\PaymentStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\ActiveState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\BookingLifecycleState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CancelledState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CompletedState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\ConfirmedState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CustomerReviewState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\DraftState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\SubmittedState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\VendorReviewState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\BookingPaymentState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\PaidState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\PartialState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\PartiallyRefundedState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\RefundPendingState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\RefundedState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\UnpaidState;
use App\Modules\Booking\Filament\Resources\BookingResource\Pages;
use App\Modules\Booking\Filament\Resources\BookingResource\RelationManagers;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?string $recordTitleAttribute = 'reference_no';

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
            ->with(['customer', 'occasion', 'vendors.vendor'])
            ->withCount(['vendors', 'items', 'snapshots']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'booking_manager', 'super_admin']) === true;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'booking_manager', 'super_admin']) === true;
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
                    ->getStateUsing(fn (Booking $record): string => self::resolveCustomerName($record))
                    ->description(fn (Booking $record): ?string => self::resolveCustomerPhone($record))
                    ->tooltip(fn (Booking $record): ?string => self::resolveCustomerPhone($record)),
                Tables\Columns\TextColumn::make('occasion_id')
                    ->label(__('booking.columns.occasion_id'))
                    ->sortable()
                    ->getStateUsing(fn (Booking $record): string => self::resolveTranslatedLabel($record->occasion, 'name') ?? '#'.$record->occasion_id),
                Tables\Columns\TextColumn::make('lifecycle_status')
                    ->label(__('booking.columns.lifecycle_status'))
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof BookingLifecycleState
                        ? __('booking.lifecycle_status.'.$state->getValue())
                        : (string) $state),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label(__('booking.columns.payment_status'))
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof BookingPaymentState
                        ? __('booking.payment_status.'.$state->getValue())
                        : (string) $state),
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
            ->recordUrl(fn (Booking $record): string => static::getUrl('view', ['record' => $record]))
            ->actions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make(__('booking.sections.summary'))
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('public_id')
                            ->label(__('booking.columns.public_id'))
                            ->copyable(),
                        TextEntry::make('reference_no')
                            ->label(__('booking.columns.reference_no')),
                        TextEntry::make('customer.name')
                            ->label(__('booking.columns.customer_id'))
                            ->getStateUsing(fn (Booking $record): string => self::resolveCustomerName($record)),
                        TextEntry::make('customer.phone_e164')
                            ->label(__('booking.columns.customer_phone'))
                            ->getStateUsing(fn (Booking $record): ?string => self::resolveCustomerPhone($record)),
                        TextEntry::make('occasion_id')
                            ->label(__('booking.columns.occasion_id'))
                            ->getStateUsing(fn (Booking $record): string => self::resolveTranslatedLabel($record->occasion, 'name') ?? '#'.$record->occasion_id),
                        TextEntry::make('guest_count')
                            ->label(__('booking.columns.guest_count')),
                        TextEntry::make('lifecycle_status')
                            ->label(__('booking.columns.lifecycle_status'))
                            ->badge()
                            ->color(fn (mixed $state): string => match (true) {
                                $state instanceof DraftState => 'gray',
                                $state instanceof SubmittedState,
                                $state instanceof VendorReviewState,
                                $state instanceof CustomerReviewState => 'warning',
                                $state instanceof ConfirmedState,
                                $state instanceof ActiveState => 'info',
                                $state instanceof CompletedState => 'success',
                                $state instanceof CancelledState => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (mixed $state): string => $state instanceof BookingLifecycleState
                                ? __('booking.lifecycle_status.'.$state->getValue())
                                : (string) $state),
                        TextEntry::make('payment_status')
                            ->label(__('booking.columns.payment_status'))
                            ->badge()
                            ->color(fn (mixed $state): string => match (true) {
                                $state instanceof UnpaidState,
                                $state instanceof PartialState => 'warning',
                                $state instanceof PaidState => 'success',
                                $state instanceof RefundPendingState => 'info',
                                $state instanceof PartiallyRefundedState => 'gray',
                                $state instanceof RefundedState => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (mixed $state): string => $state instanceof BookingPaymentState
                                ? __('booking.payment_status.'.$state->getValue())
                                : (string) $state),
                        TextEntry::make('fulfillment_status')
                            ->label(__('booking.columns.fulfillment_status'))
                            ->badge()
                            ->color(fn (FulfillmentStatus $state): string => match ($state) {
                                FulfillmentStatus::NotStarted => 'warning',
                                FulfillmentStatus::InProgress, FulfillmentStatus::PartiallyCompleted => 'info',
                                FulfillmentStatus::Completed => 'success',
                                FulfillmentStatus::Failed => 'danger',
                            })
                            ->formatStateUsing(fn (FulfillmentStatus $state): string => __('booking.fulfillment_status.'.$state->value)),
                        TextEntry::make('total_minor')
                            ->label(__('booking.columns.total'))
                            ->money('EGP', divideBy: 100),
                        TextEntry::make('amount_paid_minor')
                            ->label(__('booking.columns.amount_paid'))
                            ->money('EGP', divideBy: 100),
                        TextEntry::make('event_starts_at')
                            ->label(__('booking.columns.event_starts_at'))
                            ->dateTime(),
                        TextEntry::make('event_ends_at')
                            ->label(__('booking.columns.event_ends_at'))
                            ->dateTime(),
                        TextEntry::make('submitted_at')
                            ->label(__('booking.columns.submitted_at'))
                            ->dateTime(),
                        TextEntry::make('created_at')
                            ->label(__('admin.common.created_at'))
                            ->dateTime(),
                    ]),
                ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\BookingVendorsRelationManager::class,
            RelationManagers\BookingItemsRelationManager::class,
            RelationManagers\BookingAddressesRelationManager::class,
            RelationManagers\PaymentsRelationManager::class,
            RelationManagers\BookingSnapshotsRelationManager::class,
            RelationManagers\BookingStateTransitionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookings::route('/'),
            'view' => Pages\ViewBooking::route('/{record}'),
        ];
    }

    private static function resolveCustomerName(Booking $record): string
    {
        return $record->customer?->name ?? '#'.$record->customer_id;
    }

    private static function resolveCustomerPhone(Booking $record): ?string
    {
        return $record->customer?->phone_e164;
    }

    private static function resolveTranslatedLabel(?object $record, string $attribute): ?string
    {
        if ($record === null || ! method_exists($record, 'getTranslation')) {
            return null;
        }

        $locale = app()->getLocale();
        $value = $record->getTranslation($attribute, $locale, false);

        if (! is_string($value) || $value === '') {
            $value = $record->getTranslation($attribute, 'en', false);
        }

        while (is_array($value)) {
            $value = $value[$locale] ?? $value['en'] ?? reset($value);
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
