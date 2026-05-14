<?php

declare(strict_types=1);

namespace App\Modules\Booking\Filament\Vendor\Pages;

use App\Modules\Booking\Application\Actions\MarkBookingItemStateAction;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\States\DigitalItemStatus\PendingState as DigitalPending;
use App\Modules\Booking\Domain\States\DigitalItemStatus\RedeemedState;
use App\Modules\Booking\Domain\States\DigitalItemStatus\SentState;
use App\Modules\Booking\Domain\States\RentalItemStatus\DeliveredState;
use App\Modules\Booking\Domain\States\RentalItemStatus\OutForDeliveryState as RentalOutForDelivery;
use App\Modules\Booking\Domain\States\RentalItemStatus\PendingDeliveryState;
use App\Modules\Booking\Domain\States\RentalItemStatus\PickedUpState;
use App\Modules\Booking\Domain\States\RentalItemStatus\SetupCompleteState;
use App\Modules\Booking\Domain\States\RentalItemStatus\TeardownState;
use App\Modules\Booking\Domain\States\SaleItemStatus\DeliveredState as SaleDelivered;
use App\Modules\Booking\Domain\States\SaleItemStatus\InPreparationState;
use App\Modules\Booking\Domain\States\SaleItemStatus\OutForDeliveryState as SaleOutForDelivery;
use App\Modules\Booking\Domain\States\SaleItemStatus\PendingState as SalePending;
use App\Modules\Booking\Domain\States\SaleItemStatus\ReadyState;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class VendorActiveBookingsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationGroup = 'bookings';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'vendor-portal.pages.vendor-active-bookings';

    public function getTitle(): string|Htmlable
    {
        return __('vendor-portal.bookings.active_title');
    }

    public static function getNavigationLabel(): string
    {
        return __('vendor-portal.bookings.active_title');
    }

    public function table(Table $table): Table
    {
        $vendorProfile = $this->getVendorProfile();
        $terminalStates = [PickedUpState::$name, SaleDelivered::$name, RedeemedState::$name];

        return $table
            ->query(
                BookingItem::query()
                    ->whereHas('bookingVendor', fn ($q) => $q
                        ->where('vendor_profile_id', $vendorProfile->id)
                        ->where('sub_status', VendorSubStatus::Accepted)
                    )
                    ->whereNotIn('item_status', $terminalStates)
                    ->with(['bookingVendor.booking'])
            )
            ->columns([
                TextColumn::make('bookingVendor.booking.reference_no')
                    ->label(__('vendor-portal.bookings.reference'))
                    ->searchable(),
                TextColumn::make('product_type')
                    ->label(__('catalog.product_type'))
                    ->badge()
                    ->color(fn (ProductType $state) => match ($state) {
                        ProductType::Rental => 'warning',
                        ProductType::Sale => 'success',
                        ProductType::Digital => 'info',
                    })
                    ->formatStateUsing(fn (ProductType $state) => ucfirst($state->value)),
                TextColumn::make('name_snapshot')
                    ->label(__('catalog.name'))
                    ->getStateUsing(fn (BookingItem $record) => $record->name_snapshot[app()->getLocale()] ?? $record->name_snapshot['en'] ?? '—')
                    ->limit(30),
                TextColumn::make('item_status')
                    ->label(__('booking.item_status'))
                    ->badge()
                    ->color('info'),
                TextColumn::make('bookingVendor.booking.event_starts_at')
                    ->label(__('vendor-portal.bookings.event_date'))
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->actions([
                TableAction::make('view_details')
                    ->label(__('vendor-portal.bookings.view_details'))
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (BookingItem $record) => VendorBookingDetailPage::getUrl([
                        'bookingVendor' => $record->bookingVendor->public_id,
                    ])),

                $this->makeTransitionAction('mark_in_transit', PendingDeliveryState::$name, RentalOutForDelivery::class),
                $this->makeTransitionAction('mark_setup_started', RentalOutForDelivery::$name, DeliveredState::class),
                $this->makeTransitionAction('mark_active', DeliveredState::$name, SetupCompleteState::class),
                $this->makeTransitionAction('mark_teardown_started', SetupCompleteState::$name, TeardownState::class),
                $this->makeTransitionAction('mark_completed', TeardownState::$name, PickedUpState::class),
                $this->makeTransitionAction('mark_in_preparation', SalePending::$name, InPreparationState::class),
                $this->makeTransitionAction('mark_ready', InPreparationState::$name, ReadyState::class),
                $this->makeTransitionAction('mark_out_for_delivery', ReadyState::$name, SaleOutForDelivery::class),
                $this->makeTransitionAction('mark_delivered', SaleOutForDelivery::$name, SaleDelivered::class),
                $this->makeTransitionAction('mark_sent', DigitalPending::$name, SentState::class),
                $this->makeTransitionAction('mark_redeemed', SentState::$name, RedeemedState::class),
            ]);
    }

    /** @param class-string $toStateClass */
    private function makeTransitionAction(string $labelKey, string $visibleWhenStatus, string $toStateClass): TableAction
    {
        return TableAction::make($labelKey)
            ->label(__('vendor-portal.fulfillment.'.$labelKey))
            ->icon('heroicon-o-check-circle')
            ->color('primary')
            ->visible(fn (BookingItem $record) => $record->item_status === $visibleWhenStatus)
            ->action(function (BookingItem $record) use ($toStateClass, $labelKey): void {
                app(MarkBookingItemStateAction::class)->execute($record, $this->getVendorProfile(), $toStateClass);
                Notification::make()->title(__('vendor-portal.fulfillment.'.$labelKey))->success()->send();
            });
    }

    private function getVendorProfile(): VendorProfile
    {
        return auth()->user()->vendorProfile;
    }
}
